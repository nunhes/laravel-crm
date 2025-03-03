<?php

namespace VentureDrake\LaravelCrm;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use VentureDrake\LaravelCrm\Console\LaravelCrmAddressTypes;
use VentureDrake\LaravelCrm\Console\LaravelCrmInstall;
use VentureDrake\LaravelCrm\Console\LaravelCrmLabels;
use VentureDrake\LaravelCrm\Console\LaravelCrmOrganisationTypes;
use VentureDrake\LaravelCrm\Console\LaravelCrmPermissions;
use VentureDrake\LaravelCrm\Http\Livewire\LiveAddressEdit;
use VentureDrake\LaravelCrm\Http\Livewire\LiveEmailEdit;
use VentureDrake\LaravelCrm\Http\Livewire\LiveLeadForm;
use VentureDrake\LaravelCrm\Http\Livewire\LiveNote;
use VentureDrake\LaravelCrm\Http\Livewire\LivePhoneEdit;
use VentureDrake\LaravelCrm\Http\Livewire\LiveRelatedContactOrganisation;
use VentureDrake\LaravelCrm\Http\Livewire\LiveRelatedContactPerson;
use VentureDrake\LaravelCrm\Http\Livewire\LiveRelatedPerson;
use VentureDrake\LaravelCrm\Http\Middleware\Authenticate;
use VentureDrake\LaravelCrm\Http\Middleware\HasCrmAccess;
use VentureDrake\LaravelCrm\Http\Middleware\LastOnlineAt;
use VentureDrake\LaravelCrm\Http\Middleware\Settings;
use VentureDrake\LaravelCrm\Http\Middleware\SystemCheck;
use VentureDrake\LaravelCrm\Http\Middleware\TeamsPermission;
use VentureDrake\LaravelCrm\Models\Contact;
use VentureDrake\LaravelCrm\Models\Email;
use VentureDrake\LaravelCrm\Models\Lead;
use VentureDrake\LaravelCrm\Models\Note;
use VentureDrake\LaravelCrm\Models\Organisation;
use VentureDrake\LaravelCrm\Models\Person;
use VentureDrake\LaravelCrm\Models\Phone;
use VentureDrake\LaravelCrm\Models\Setting;
use VentureDrake\LaravelCrm\Observers\ContactObserver;
use VentureDrake\LaravelCrm\Observers\EmailObserver;
use VentureDrake\LaravelCrm\Observers\LeadObserver;
use VentureDrake\LaravelCrm\Observers\NoteObserver;
use VentureDrake\LaravelCrm\Observers\OrganisationObserver;
use VentureDrake\LaravelCrm\Observers\PersonObserver;
use VentureDrake\LaravelCrm\Observers\PhoneObserver;
use VentureDrake\LaravelCrm\Observers\SettingObserver;
use VentureDrake\LaravelCrm\Observers\TeamObserver;
use VentureDrake\LaravelCrm\Observers\UserObserver;

class LaravelCrmServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array
     */
    protected $policies = [
        'App\User' => \VentureDrake\LaravelCrm\Policies\UserPolicy::class,
        'App\Models\User' => \VentureDrake\LaravelCrm\Policies\UserPolicy::class,
        'VentureDrake\LaravelCrm\Models\Team' => \VentureDrake\LaravelCrm\Policies\TeamPolicy::class,
        'VentureDrake\LaravelCrm\Models\Setting' => \VentureDrake\LaravelCrm\Policies\SettingPolicy::class,
        'VentureDrake\LaravelCrm\Models\Role' => \VentureDrake\LaravelCrm\Policies\RolePolicy::class,
        'VentureDrake\LaravelCrm\Models\Permission' => \VentureDrake\LaravelCrm\Policies\PermissionPolicy::class,
        'VentureDrake\LaravelCrm\Models\Lead' => \VentureDrake\LaravelCrm\Policies\LeadPolicy::class,
        'VentureDrake\LaravelCrm\Models\Deal' => \VentureDrake\LaravelCrm\Policies\DealPolicy::class,
        'VentureDrake\LaravelCrm\Models\Person' => \VentureDrake\LaravelCrm\Policies\PersonPolicy::class,
        'VentureDrake\LaravelCrm\Models\Organisation' => \VentureDrake\LaravelCrm\Policies\OrganisationPolicy::class,
        'VentureDrake\LaravelCrm\Models\Contact' => \VentureDrake\LaravelCrm\Policies\ContactPolicy::class,
        'VentureDrake\LaravelCrm\Models\Product' => \VentureDrake\LaravelCrm\Policies\ProductPolicy::class,
        'VentureDrake\LaravelCrm\Models\ProductCategory' => \VentureDrake\LaravelCrm\Policies\ProductCategoryPolicy::class,
        'VentureDrake\LaravelCrm\Models\Label' => \VentureDrake\LaravelCrm\Policies\LabelPolicy::class,
    ];
    
    /**
     * Bootstrap the application services.
     */
    public function boot(Router $router, Filesystem $filesystem)
    {
        if ((app()->version() >= 8 && class_exists('App\Models\User')) || (class_exists('App\Models\User') && ! class_exists('App\User'))) {
            class_alias(config("auth.providers.users.model"), 'App\User');
            if (class_exists('App\Models\Team')) {
                class_alias('App\Models\Team', 'App\Team');
            }
        }
        
        $this->registerPolicies();
        
        /*
         * Optional methods to load your package assets
         */
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'laravel-crm');
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'laravel-crm');
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        // Middleware
        $router->aliasMiddleware('auth.laravel-crm', Authenticate::class);
        
        if (config('laravel-crm.teams')) {
            $router->pushMiddlewareToGroup('web', TeamsPermission::class);
            $router->pushMiddlewareToGroup('crm-api', TeamsPermission::class);
        }
        
        $router->pushMiddlewareToGroup('crm', Settings::class);
        $router->pushMiddlewareToGroup('crm-api', Settings::class);
        $router->pushMiddlewareToGroup('crm', HasCrmAccess::class);
        $router->pushMiddlewareToGroup('crm-api', HasCrmAccess::class);
        $router->pushMiddlewareToGroup('crm', LastOnlineAt::class);
        $router->pushMiddlewareToGroup('crm', SystemCheck::class);
        
        $this->registerRoutes();

        // Register Observers
        Lead::observe(LeadObserver::class);
        Person::observe(PersonObserver::class);
        Organisation::observe(OrganisationObserver::class);
        Phone::observe(PhoneObserver::class);
        Email::observe(EmailObserver::class);
        Setting::observe(SettingObserver::class);
        Note::observe(NoteObserver::class);
        Contact::observe(ContactObserver::class);
        
        if (class_exists('App\Models\User')) {
            \App\Models\User::observe(UserObserver::class);
        } else {
            \App\User::observe(UserObserver::class);
        }

        if (class_exists('App\Models\Team')) {
            \App\Models\Team::observe(TeamObserver::class);
        } elseif (class_exists('App\Team')) {
            \App\Team::observe(TeamObserver::class);
        }
        
        // Paginate on Collection
        if (! Collection::hasMacro('paginate')) {
            Collection::macro(
                'paginate',
                function ($perPage = 30, $page = null, $options = []) {
                    $page = $page ?: (Paginator::resolveCurrentPage() ?: 1);

                    return (new LengthAwarePaginator(
                        $this->forPage($page, $perPage),
                        $this->count(),
                        $perPage,
                        $page,
                        $options
                    ))
                        ->withPath('');
                }
            );
        }

        if ($this->app->runningInConsole()) {
            if (app()->version() >= 8.6) {
                $auditConfig = '/../config/audit-sanctum.php';
            } else {
                $auditConfig = '/../config/audit.php';
            }
            
            $this->publishes([
                __DIR__ . '/../config/laravel-crm.php' => config_path('laravel-crm.php'),
                __DIR__ . '/../config/permission.php' => config_path('permission.php'),
                __DIR__ . $auditConfig => config_path('audit.php'),
                __DIR__ . '/../config/columnsortable.php' => config_path('columnsortable.php'),
            ], 'config');

            // Publishing the views.
            $this->publishes([
                __DIR__.'/../resources/views' => resource_path('views/vendor/laravel-crm'),
            ], 'views');

            // Publishing assets.
            $this->publishes([
                __DIR__.'/../resources/assets' => public_path('vendor/laravel-crm'),
            ], 'assets');

            // Publishing the translation files.
            $this->publishes([
                __DIR__.'/../resources/lang' => resource_path('lang/vendor/laravel-crm'),
            ], 'lang');

            // Publishing the migrations.
            $this->publishes([
                __DIR__ . '/../database/migrations/create_permission_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_permission_tables.php', 1), // Spatie Permissions
                __DIR__ . '/../database/migrations/add_teams_fields.php.stub' => $this->getMigrationFileName($filesystem, 'add_teams_fields.php', 2), // Spatie Permissions
                __DIR__ . '/../database/migrations/create_laravel_crm_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_tables.php', 3),
                __DIR__ . '/../database/migrations/create_laravel_crm_settings_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_settings_table.php', 4),
                __DIR__ . '/../database/migrations/add_fields_to_roles_permissions_tables.php.stub' => $this->getMigrationFileName($filesystem, 'add_fields_to_roles_permissions_tables.php', 5),
                __DIR__ . '/../database/migrations/add_label_editable_fields_to_laravel_crm_settings_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_label_editable_fields_to_laravel_crm_settings_table.php', 6),
                __DIR__ . '/../database/migrations/add_team_id_to_laravel_crm_tables.php.stub' => $this->getMigrationFileName($filesystem, 'add_team_id_to_laravel_crm_tables.php', 7),
                __DIR__ . '/../database/migrations/create_laravel_crm_products_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_products_table.php', 8),
                __DIR__ . '/../database/migrations/create_laravel_crm_product_categories_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_product_categories_table.php', 9),
                __DIR__ . '/../database/migrations/create_laravel_crm_product_prices_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_product_prices_table.php', 10),
                __DIR__ . '/../database/migrations/create_laravel_crm_product_variations_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_product_variations_table.php', 11),
                __DIR__ . '/../database/migrations/create_laravel_crm_deal_products_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_deal_products_table.php', 12),
                __DIR__ . '/../database/migrations/add_global_to_laravel_crm_settings_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_global_to_laravel_crm_settings_table.php', 13),
                __DIR__ . '/../database/migrations/alter_fields_for_encryption_on_laravel_crm_tables.php.stub' => $this->getMigrationFileName($filesystem, 'alter_fields_for_encryption_on_laravel_crm_tables.php', 14),
                __DIR__ . '/../database/migrations/create_laravel_crm_address_types_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_address_types_table.php', 15),
                __DIR__ . '/../database/migrations/alter_type_on_laravel_crm_phones_table.php.stub' => $this->getMigrationFileName($filesystem, 'alter_type_on_laravel_crm_phones_table.php', 16),
                __DIR__ . '/../database/migrations/add_description_to_laravel_crm_labels_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_description_to_laravel_crm_labels_table.php', 17),
                __DIR__ . '/../database/migrations/add_name_to_laravel_crm_addresses_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_name_to_laravel_crm_addresses_table.php', 18),
                __DIR__ . '/../database/migrations/create_laravel_crm_contacts_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_contacts_table.php', 19),
                __DIR__ . '/../database/migrations/create_laravel_crm_contact_types_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_contact_types_table.php', 20),
                __DIR__ . '/../database/migrations/create_laravel_crm_contact_contact_type_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_contact_contact_type_table.php', 21),
                __DIR__ . '/../database/migrations/create_audits_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_audits_table.php', 22), // Laravel auditing
                __DIR__ . '/../database/migrations/create_devices_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_devices_table.php', 23), // Laravel Auth Checker
                __DIR__ . '/../database/migrations/create_logins_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_logins_table.php', 24), // Laravel Auth Checker
                __DIR__ . '/../database/migrations/update_logins_and_devices_table_user_relation.php.stub' => $this->getMigrationFileName($filesystem, 'update_logins_and_devices_table_user_relation.php', 25), // Laravel Auth Checker
                __DIR__ . '/../database/migrations/create_laravel_crm_organisation_types_table.php.stub' => $this->getMigrationFileName($filesystem, 'create_laravel_crm_organisation_types_table.php', 26),
                __DIR__ . '/../database/migrations/change_morph_col_names_on_laravel_crm_notes_table.php.stub' => $this->getMigrationFileName($filesystem, 'change_morph_col_names_on_laravel_crm_notes_table.php', 27),
                __DIR__ . '/../database/migrations/add_related_note_to_laravel_crm_notes_table.php.stub' => $this->getMigrationFileName($filesystem, 'add_related_note_to_laravel_crm_notes_table.php', 28),
            ], 'migrations');

            // Publishing the seeders
            if (! class_exists('LaravelCrmTablesSeeder')) {
                if (app()->version() >= 8) {
                    $this->publishes([
                        __DIR__ . '/../database/seeders/LaravelCrmTablesSeeder.php' => database_path(
                            'seeders/LaravelCrmTablesSeeder.php'
                        ),
                    ], 'seeders');
                } else {
                    $this->publishes([
                        __DIR__ . '/../database/seeders/LaravelCrmTablesSeeder.php' => database_path(
                            'seeds/LaravelCrmTablesSeeder.php'
                        ),
                    ], 'seeders');
                }
            }

            // Registering package commands.
            $this->commands([
                LaravelCrmInstall::class,
                LaravelCrmPermissions::class,
                LaravelCrmLabels::class,
                LaravelCrmAddressTypes::class,
                LaravelCrmOrganisationTypes::class,
            ]);

            // Register the model factories
            if (app()->version() < 8) {
                $this->app->make('Illuminate\Database\Eloquent\Factory')
                     ->load(__DIR__.'/../database/factories');
            }
        }
        
        // Livewire components
        Livewire::component('phone-edit', LivePhoneEdit::class);
        Livewire::component('email-edit', LiveEmailEdit::class);
        Livewire::component('address-edit', LiveAddressEdit::class);
        Livewire::component('notes', LiveNote::class);
        Livewire::component('related-contact-organisations', LiveRelatedContactOrganisation::class);
        Livewire::component('related-contact-people', LiveRelatedContactPerson::class);
        Livewire::component('related-people', LiveRelatedPerson::class);
        Livewire::component('live-lead-form', LiveLeadForm::class);
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        // Automatically apply the package configuration
        $this->mergeConfigFrom(__DIR__ . '/../config/package.php', 'laravel-crm');
        $this->mergeConfigFrom(__DIR__ . '/../config/laravel-crm.php', 'laravel-crm');

        // Register the main class to use with the facade
        $this->app->singleton('laravel-crm', function () {
            return new LaravelCrm;
        });

        $this->app->register(LaravelCrmEventServiceProvider::class);
    }
    
    protected function registerRoutes()
    {
        Route::group($this->routeConfiguration(), function () {
            $this->loadRoutesFrom(__DIR__ . '/Http/routes.php');
        });
    }
    
    protected function routeConfiguration()
    {
        return [
            'prefix' => config('laravel-crm.route_prefix'),
            'middleware' => array_unique(array_merge(['web','crm','crm-api'], config('laravel-crm.route_middleware') ?? [])),
        ];
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param Filesystem $filesystem
     * @return string
     */
    protected function getMigrationFileName(Filesystem $filesystem, $filename, $order): string
    {
        $timestamp = date('Y_m_d_His', strtotime("+$order sec"));

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $filename) {
                return $filesystem->glob($path.'*_'.$filename);
            })->push($this->app->databasePath()."/migrations/{$timestamp}_".$filename)
            ->first();
    }

    /**
     * Register the application's policies.
     *
     * @return void
     */
    public function registerPolicies()
    {
        foreach ($this->policies() as $key => $value) {
            Gate::policy($key, $value);
        }
    }

    /**
     * Get the policies defined on the provider.
     *
     * @return array
     */
    public function policies()
    {
        return $this->policies;
    }
}
