<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::dropIfExists('binshops_post_categories');
        Schema::dropIfExists('binshops_post_translations');
        Schema::dropIfExists('binshops_comments');
        Schema::dropIfExists('binshops_uploaded_photos');
        Schema::dropIfExists('binshops_category_translations');
        Schema::dropIfExists('binshops_posts');
        Schema::dropIfExists('binshops_categories');
        Schema::dropIfExists('binshops_languages');
        Schema::dropIfExists('binshops_configurations');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('binshops_languages', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name')->unique();
            $table->string('locale')->unique();
            $table->string('iso_code')->unique();
            $table->string('date_format');
            $table->boolean('active')->default(true);
            $table->boolean('rtl')->default(false);
            $table->timestamps();
        });

        Schema::create('binshops_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('created_by')->nullable()->index()->comment('user id');
            $table->integer('parent_id')->nullable();
            $table->integer('lft')->nullable();
            $table->integer('rgt')->nullable();
            $table->integer('depth')->nullable();
            $table->timestamps();
        });

        Schema::create('binshops_category_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('category_id')->nullable();
            $table->string('category_name')->nullable();
            $table->string('slug')->unique();
            $table->mediumText('category_description')->nullable();
            $table->unsignedInteger('lang_id')->index();
            $table->foreign('lang_id')->references('id')->on('binshops_languages');
            $table->timestamps();
        });

        Schema::create('binshops_posts', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('user_id')->index()->nullable();
            $table->dateTime('posted_at')->index()->nullable()->comment('Public posted at time, if this is in future then it wont appear yet');
            $table->boolean('is_published')->default(true);
            $table->timestamps();
        });

        Schema::create('binshops_post_categories', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id')->index();
            $table->foreign('post_id')->references('id')->on('binshops_posts')->onDelete('cascade');
            $table->unsignedInteger('category_id')->index();
            $table->foreign('category_id')->references('id')->on('binshops_categories')->onDelete('cascade');
        });

        Schema::create('binshops_post_translations', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id')->nullable();
            $table->string('slug');
            $table->string('title')->nullable()->default('New blog post');
            $table->string('subtitle')->nullable()->default('');
            $table->text('meta_desc')->nullable();
            $table->string('seo_title')->nullable();
            $table->mediumText('post_body')->nullable();
            $table->text('short_description')->nullable();
            $table->string('use_view_file')->nullable()->comment('should refer to a blade file in /views/');
            $table->string('image_large')->nullable();
            $table->string('image_medium')->nullable();
            $table->string('image_thumbnail')->nullable();
            $table->unsignedInteger('lang_id')->index();
            $table->foreign('lang_id')->references('id')->on('binshops_languages');
            $table->timestamps();
        });

        Schema::create('binshops_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('post_id')->index();
            $table->unsignedInteger('user_id')->nullable()->index()->comment('if user was logged in');
            $table->string('ip')->nullable()->comment('if enabled in the config file');
            $table->string('author_name')->nullable()->comment('if not logged in');
            $table->text('comment')->comment('the comment body');
            $table->boolean('approved')->default(true);
            $table->string('author_email')->nullable();
            $table->string('author_website')->nullable();
            $table->timestamps();
        });

        Schema::create('binshops_uploaded_photos', function (Blueprint $table) {
            $table->increments('id');
            $table->text('uploaded_images')->nullable();
            $table->string('image_title')->nullable();
            $table->string('source')->default('unknown');
            $table->unsignedInteger('uploader_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('binshops_configurations', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('value');
            $table->timestamps();
        });
    }
};
