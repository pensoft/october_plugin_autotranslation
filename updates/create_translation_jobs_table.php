<?php namespace Pensoft\AutoTranslation\Updates;

use Schema;
use October\Rain\Database\Updates\Migration;

/**
 * Create Translation Jobs Table
 * For future queue/batch job tracking
 */
class CreateTranslationJobsTable extends Migration
{
    public function up()
    {
        Schema::create('pensoft_autotranslation_jobs', function($table)
        {
            $table->engine = 'InnoDB';
            $table->increments('id');
            $table->string('job_type', 50)->index(); // 'message', 'model'
            $table->string('source_locale', 10);
            $table->string('target_locale', 10);
            $table->string('model_class')->nullable();
            $table->integer('total_items')->default(0);
            $table->integer('processed_items')->default(0);
            $table->integer('successful_items')->default(0);
            $table->integer('failed_items')->default(0);
            $table->string('status', 20)->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->text('options')->nullable(); // JSON for additional options
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('pensoft_autotranslation_jobs');
    }
}

