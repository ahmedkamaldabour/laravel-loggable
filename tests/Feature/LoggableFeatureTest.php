<?php

namespace Devdabour\LaravelLoggable\Tests\Feature;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase;
use Spatie\Activitylog\Models\Activity;
use Devdabour\LaravelLoggable\Providers\LoggableServiceProvider;
use Devdabour\LaravelLoggable\Tests\TestUser;
use Devdabour\LaravelLoggable\Traits\Loggable;

class LoggableFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tables
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 8, 2);
            $table->string('status')->default('draft');
            $table->integer('category_id')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
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
        Schema::dropIfExists('products');
        Schema::dropIfExists('categories');
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

    public function test_real_world_scenario_with_relationships()
    {
        // Create a category
        $category = Category::create(['name' => 'Electronics']);

        // Create a product with relationship
        $product = Product::create([
            'name' => 'Smartphone',
            'description' => 'Latest model with great features',
            'price' => 999.99,
            'status' => 'active',
            'category_id' => $category->id
        ]);

        // Load the relationship for logging
        $product->load('category');

        // Update the product
        $product->update([
            'price' => 899.99,
            'status' => 'on_sale'
        ]);

        // Check that the activity was logged properly
        $activity = Activity::where('subject_type', Product::class)
            ->where('subject_id', $product->id)
            ->where('event', 'updated')
            ->latest()
            ->first();

        $this->assertNotNull($activity);

        // Check that attributes were properly tracked
        $properties = $activity->properties->toArray();
        $this->assertEquals(899.99, $properties['attributes']['Price']);
        $this->assertEquals('on_sale', $properties['attributes']['Status']);
        $this->assertEquals(999.99, $properties['old']['Price']);
        $this->assertEquals('active', $properties['old']['Status']);

        // Check that relationship data was included in metadata
        $this->assertArrayHasKey('metadata', $properties);
        $this->assertArrayHasKey('related', $properties['metadata']);
        $this->assertArrayHasKey('category', $properties['metadata']['related']);
        $this->assertEquals('Electronics', $properties['metadata']['related']['category']['name']);
    }

    public function test_handling_multiple_updates_history()
    {
        // Create a product
        $product = Product::create([
            'name' => 'Laptop',
            'description' => 'Powerful workstation',
            'price' => 1299.99,
            'status' => 'draft'
        ]);

        // Perform multiple updates to track history
        $product->update(['status' => 'pending_review']);
        $product->update(['status' => 'approved']);
        $product->update(['status' => 'active']);
        $product->update(['price' => 1199.99, 'status' => 'on_sale']);

        // Get all activities for this product
        $activities = Activity::where('subject_type', Product::class)
            ->where('subject_id', $product->id)
            ->orderBy('created_at')
            ->get();

        // Should have 5 activities (1 create + 4 updates)
        $this->assertEquals(5, $activities->count());

        // Check the status progression through activities
        $statusProgression = $activities->map(function ($activity) {
            if ($activity->event === 'created') {
                return $activity->properties['attributes']['Status'] ?? null;
            } else {
                return $activity->properties['attributes']['Status'] ?? null;
            }
        })->filter()->values()->toArray();

        $this->assertEquals([
            'draft',
            'pending_review',
            'approved',
            'active',
            'on_sale'
        ], $statusProgression);
    }
}

class Product extends Model
{
    use Loggable;

    protected $guarded = [];

    protected static array $logAttributes = [
        'name' => 'Product Name',
        'description' => 'Description',
        'price' => 'Price',
        'status' => 'Status',
    ];

    protected static $logName = 'Product';
    protected static $logMetadata = true;
    protected static $logRelationships = [
        'category' => ['id', 'name'],
    ];

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}

class Category extends Model
{
    use Loggable;

    protected $guarded = [];

    protected static array $logAttributes = [
        'name' => 'Category Name',
    ];

    protected static $logName = 'Category';
}
