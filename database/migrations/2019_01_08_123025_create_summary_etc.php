<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSummaryEtc extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $schema->create('custom_view_summaries', function (ExtendedBlueprint $table) {
            $table->increments('id');
            $table->integer('custom_view_id')->unsigned();
            $table->integer('view_column_type')->default(0);
            $table->integer('view_column_target_id')->nullable();
            $table->integer('view_summary_condition')->unsigned()->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->timeusers();

            $table->foreign('custom_view_id')->references('id')->on('custom_views');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('custom_view_summaries');
    }
}
