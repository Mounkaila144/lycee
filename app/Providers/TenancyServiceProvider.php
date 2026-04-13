<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

class TenancyServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Événement : Initialisation du tenant (switch DB)
        Event::listen(
            \Stancl\Tenancy\Events\TenancyInitialized::class,
            function ($event) {
                $tenant = $event->tenancy->tenant;

                // Utiliser la méthode database()->getName() du package stancl/tenancy
                $databaseName = $tenant->database()->getName();

                // Récupérer la config de la connexion centrale comme base
                $centralConfig = config('database.connections.mysql');

                // Configuration dynamique de la connexion tenant
                config([
                    'database.connections.tenant.database' => $databaseName,
                    'database.connections.tenant.host' => $tenant->site_db_host ?? $centralConfig['host'],
                    'database.connections.tenant.username' => $tenant->site_db_username ?? $centralConfig['username'],
                    'database.connections.tenant.password' => $tenant->site_db_password ?? $centralConfig['password'],
                ]);

                // Purger et reconnecter
                DB::purge('tenant');
                DB::reconnect('tenant');

                // Définir comme connexion par défaut
                DB::setDefaultConnection('tenant');

                // Logger pour debug
                if (config('app.debug')) {
                    logger()->info("Tenancy initialized", [
                        'tenant_id' => $tenant->id,
                        'database' => $databaseName,
                    ]);
                }
            }
        );

        // Événement : Fin du tenant (retour à central)
        Event::listen(
            \Stancl\Tenancy\Events\TenancyEnded::class,
            function () {
                // Revenir à la connexion centrale
                DB::setDefaultConnection('mysql');

                if (config('app.debug')) {
                    logger()->info("Tenancy ended, switched back to central DB");
                }
            }
        );

        // Événement : Création d'un nouveau tenant
        Event::listen(
            \Stancl\Tenancy\Events\TenantCreated::class,
            function ($event) {
                logger()->info("Tenant created", [
                    'tenant_id' => $event->tenant->site_id,
                    'host' => $event->tenant->site_host,
                ]);
            }
        );
    }
}
