<?php

namespace Exceedone\Exment\Console;

use Illuminate\Console\Command;
use Exceedone\Exment\Model\System;
use Exceedone\Exment\Enums\BackupTarget;

class BackupCommand extends Command
{
    use CommandTrait, BackupRestoreTrait;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'exment:backup {--target=} {--schedule}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Backup database definition, table data, files in selected folder';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->initExmentCommand();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try{
            $target = $this->option("target") ?? BackupTarget::arrays();

            if (is_string($target)) {
                $target = collect(explode(",", $target))->map(function ($t) {
                    return new BackupTarget($t) ?? null;
                })->filter()->toArray();
            }
    
            $this->initBackupRestore();
    
            // backup database tables
            if (in_array(BackupTarget::DATABASE, $target)) {
                \DB::backupDatabase($this->diskService->tmpDirFullPath());
            }
    
            // backup directory
            if (!$this->copyFiles($target)) {
                return -1;
            }
    
            // archive whole folder to zip
            $this->createZip();
    
            $this->removeOldBackups();
    
            return 0;
        }
        catch(\Exception $e){
            throw $e;
        }
        finally{
            $this->diskService->deleteTmpDirectory();
        }
    }

    /**
     * copy folder to temp directory
     *
     * @return bool true:success/false:fail
     */
    protected function copyFiles($target)
    {
        // get directory paths
        $settings = collect($target)->map(function ($val) {
            return BackupTarget::dir($val);
        })->filter(function ($val) {
            return isset($val);
        })->toArray();
        $settings = array_merge(
            config('exment.backup_info.copy_dir', []),
            $settings
        );
        
        if (is_array($settings)) {
            foreach ($settings as $setting) {
                $from = base_path($setting);
                if (!\File::exists($from)) {
                    continue;
                }

                $to = path_join($this->diskService->tmpDirName(), $setting);

                if (!$this->tmpDisk()->exists($to)) {
                    $this->tmpDisk()->makeDirectory($to, 0755, true);
                }

                $success = \File::copyDirectory($from, $this->tmpDisk()->path($to));
                if (!$success) {
                    return false;
                }
            }
                
            // if contains 'config' in $settings, copy env file
            if (in_array('config', $settings)) {
                $from_env = path_join(base_path(), '.env');
                $to_env = $this->tmpDisk()->path(path_join($this->diskService->tmpDirName(), '.env'));

                if (\File::exists($from_env)) {
                    \File::copy($from_env, $to_env);
                }
            }
        }

        return true;
    }
    
    /**
     * archive whole folder(sql and tsv only) to zip.
     *
     */
    protected function createZip()
    {
        // open new zip file
        $zip = new \ZipArchive();
        $res = $zip->open($this->diskService->tmpFileFullPath(), \ZipArchive::CREATE);

        if ($res === true) {
            // iterator all files in folder
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->diskService->tmpDirFullPath()));
            foreach ($files as $name => $file) {
                if ($file->isDir()) {
                    continue;
                }
                $filePath = $file->getRealPath();
                $relativePath = substr($filePath, strlen($this->diskService->tmpDirFullPath()) + 1);
                $zip->addFile($filePath, $relativePath);
            }
            $zip->close();
        }

        // upload file
        $this->diskService->upload($this->diskService->tmpFilePath());
    }
    
    /**
     * Remove old backup
     *
     * @return void
     */
    protected function removeOldBackups()
    {
        // get history file counts
        $backup_history_files = System::backup_history_files();
        if (!isset($backup_history_files) || $backup_history_files <= 0) {
            return;
        }

        // check whether batch
        $schedule = boolval($this->option("schedule"));
        if (!$schedule) {
            return;
        }

        $disk = $this->disk();

        // get files
        $filenames = $disk->files($this->diskService->dirName());

        // get file infos
        $files = collect($filenames)->map(function ($filename) use ($disk) {
            return [
                'name' => $filename,
                'lastModified' => $disk->lastModified($filename),
            ];
        })->sortByDesc('lastModified');

        // remove file
        foreach ($files->values()->all() as $index => $file) {
            if ($index < $backup_history_files) {
                continue;
            }

            $disk->delete(array_get($file, 'name'));
        }
    }
}
