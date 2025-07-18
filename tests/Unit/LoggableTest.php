<?php

namespace Devdabour\LaravelLoggable\Tests\Unit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\Models\Activity;
use Devdabour\LaravelLoggable\Providers\LoggableServiceProvider;
use Devdabour\LaravelLoggable\Tests\TestUser;
use Devdabour\LaravelLoggable\Traits\Loggable;

class LoggableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create test tables
        Schema::create('test_models', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            $table->timestamps();
        });

        // Create users table for authentication
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        // Create activity log table with proper schema
        (new \Devdabour\LaravelLoggable\Tests\CustomActivityLogTableMigration())->up();

        // Set up a test user for authentication
        $user = new TestUser([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Login the user
        Auth::login($user);
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_models');
        Schema::dropIfExists('users');
        Schema::dropIfExists('activity_log');

        // Logout the user
        Auth::logout();

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [
            LoggableServiceProvider::class,
            \Spatie\Activitylog\ActivitylogServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app)
    {
        // Setup default database to use sqlite :memory:
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Configure activitylog package
        $app['config']->set('activitylog.default_auth_driver', 'web');

        // Configure a basic session driver for auth
        $app['config']->set('session.driver', 'array');

        // Set up custom auth for testing
        $app['config']->set('auth.defaults.guard', 'test');
        $app['config']->set('auth.guards.test', [
            'driver' => 'session',
            'provider' => 'test-users',
        ]);
        $app['config']->set('auth.providers.test-users', [
            'driver' => 'eloquent',
            'model' => TestUser::class,
        ]);
    }

    /**
     * Define database migrations.
     *
     * @return void
     */
    protected function defineDatabaseMigrations()
    {
        $this->loadMigrationsFrom(realpath(__DIR__.'/../../vendor/spatie/laravel-activitylog/database/migrations'));
    }

    public function test_it_logs_model_changes()
    {
        // Create a test model
        $model = TestModel::create([
            'name' => 'Test Name',
            'description' => 'Test Description',
            'status' => 'draft',
        ]);
        
        // Check that activity was logged
        $activity = Activity::all()->last();
        $this->assertNotNull($activity);
        $this->assertEquals('created', $activity->event);
        $this->assertEquals('TestModel', $activity->log_name);
        
        // Check that attributes were properly mapped
        $properties = $activity->properties->toArray();
        $this->assertArrayHasKey('attributes', $properties);
        $this->assertEquals('Test Name', $properties['attributes']['Name']);
        $this->assertEquals('Test Description', $properties['attributes']['Description']);
        $this->assertEquals('draft', $properties['attributes']['Status']);
    }

    public function test_it_logs_only_dirty_attributes()
    {
        // Create a test model
        $model = TestModel::create([
            'name' => 'Test Name',
            'description' => 'Test Description',
            'status' => 'draft',
        ]);
        
        // Clear the log
        Activity::query()->delete();
        
        // Update only name
        $model->update(['name' => 'Updated Name']);
        
        // Check that only name was logged
        $activity = Activity::all()->last();
        $properties = $activity->properties->toArray();
        
        $this->assertArrayHasKey('attributes', $properties);
        $this->assertArrayHasKey('old', $properties);
        
        $this->assertEquals('Updated Name', $properties['attributes']['Name']);
        $this->assertEquals('Test Name', $properties['old']['Name']);
        
        // Verify description and status are not in the log
        $this->assertArrayNotHasKey('Description', $properties['attributes']);
        $this->assertArrayNotHasKey('Status', $properties['attributes']);
    }

    public function test_it_handles_metadata_logging()
    {
        // Create a metadata-enabled test model
        $model = MetadataEnabledTestModel::create([
            'name' => 'Test With Metadata',
            'description' => 'Description with metadata',
        ]);
        
        // Check that metadata was included
        $activity = Activity::all()->last();
        $properties = $activity->properties->toArray();
        
        $this->assertArrayHasKey('metadata', $properties);
        $this->assertArrayHasKey('context', $properties['metadata']);
        $this->assertEquals('test-model-1', $properties['metadata']['context']['Model ID']);
    }

    public function test_it_limits_large_content()
    {
        // Create a model with large text
        $largeText = str_repeat('This is a large text content that should be truncated. ', 100);
        
        $model = TestModel::create([
            'name' => 'Large Content Test',
            'description' => $largeText,
        ]);
        
        // Check that content was truncated
        $activity = Activity::all()->last();
        $properties = $activity->properties->toArray();
        
        $this->assertArrayHasKey('attributes', $properties);
        $this->assertArrayHasKey('Description', $properties['attributes']);
        
        // Content should be truncated to less than original size
        $this->assertLessThan(strlen($largeText), strlen($properties['attributes']['Description']));
    }
}

class TestModel extends Model
{
    use Loggable;
    
    protected $guarded = [];
    public $timestamps = true;
    
    protected static array $logAttributes = [
        'name' => 'Name',
        'description' => 'Description',
        'status' => 'Status',
    ];
    
    protected static $logName = 'TestModel';
}

class MetadataEnabledTestModel extends Model
{
    use Loggable;
    
    protected $table = 'test_models';
    protected $guarded = [];
    public $timestamps = true;
    
    protected static array $logAttributes = [
        'name' => 'Name',
        'description' => 'Description',
    ];
    
    protected static $logName = 'TestModelWithMetadata';
    protected static $logMetadata = true;
    protected static $logAdditionalData = [
        'custom_id' => 'Model ID',
    ];
    
    public function getCustomIdAttribute()
    {
        return 'test-model-' . $this->id;
    }
}
