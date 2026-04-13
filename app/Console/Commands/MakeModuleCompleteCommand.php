<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class MakeModuleCompleteCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'make:module-complete
                            {name? : Le nom du module (PascalCase)}
                            {--tenant : Le module utilise la base tenant}
                            {--central : Le module utilise la base centrale}
                            {--controllers=* : Les controllers à créer (admin, frontend, superadmin)}
                            {--model= : Le nom du model principal}
                            {--fields= : Les champs du model (format: name:type,email:string:unique)}
                            {--with-factory : Créer une factory}
                            {--with-seeder : Créer un seeder}
                            {--with-tests : Créer les tests}
                            {--force : Écraser les fichiers existants}';

    /**
     * The console command description.
     */
    protected $description = 'Crée un module complet avec tous les fichiers initialisés (Model, Controller, Migration, Routes, etc.)';

    /**
     * Configuration du module
     */
    protected string $moduleName;

    protected string $moduleNameLower;

    protected string $modelName;

    protected string $tableName;

    protected bool $isTenant;

    protected array $controllers;

    protected array $fields;

    protected bool $withFactory;

    protected bool $withSeeder;

    protected bool $withTests;

    protected string $modulePath;

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║       🚀 Générateur de Module Complet - CRM API              ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        // Collecter les informations
        $this->collectModuleInfo();

        // Afficher le résumé
        $this->displaySummary();

        // Confirmer la création
        if (! confirm('Voulez-vous créer ce module ?', true)) {
            $this->warn('❌ Création annulée.');

            return self::FAILURE;
        }

        $this->info('');
        $this->info('📦 Création du module en cours...');
        $this->info('');

        // Créer le module
        $this->createModule();

        // Créer les fichiers
        $this->createServiceProviders();
        $this->createConfig();
        $this->createModel();
        $this->createMigration();
        $this->createFormRequests();
        $this->createResource();
        $this->createControllers();
        $this->createRoutes();

        if ($this->withFactory) {
            $this->createFactory();
        }

        if ($this->withSeeder) {
            $this->createSeeder();
        }

        if ($this->withTests) {
            $this->createTests();
        }

        // Formater le code
        $this->formatCode();

        $this->info('');
        $this->info('╔══════════════════════════════════════════════════════════════╗');
        $this->info('║       ✅ Module créé avec succès !                           ║');
        $this->info('╚══════════════════════════════════════════════════════════════╝');
        $this->info('');

        $this->displayNextSteps();

        return self::SUCCESS;
    }

    /**
     * Collecte les informations du module (interactif ou arguments)
     */
    protected function collectModuleInfo(): void
    {
        // Nom du module
        $this->moduleName = $this->argument('name') ?? text(
            label: 'Nom du module (PascalCase)',
            placeholder: 'Products',
            required: true,
            validate: fn (string $value) => match (true) {
                strlen($value) < 2 => 'Le nom doit avoir au moins 2 caractères.',
                ! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $value) => 'Le nom doit être en PascalCase (ex: Products, OrderItems).',
                File::isDirectory(base_path("modules/{$value}")) && ! $this->option('force') => 'Ce module existe déjà.',
                default => null
            }
        );

        $this->moduleNameLower = Str::lower($this->moduleName);
        $this->modulePath = base_path("modules/{$this->moduleName}");

        // Nom du model
        $defaultModel = Str::singular($this->moduleName);
        $this->modelName = $this->option('model') ?? text(
            label: 'Nom du model principal',
            placeholder: $defaultModel,
            default: $defaultModel,
            required: true
        );

        $this->tableName = Str::snake(Str::plural($this->modelName));

        // Type de base de données
        if ($this->option('tenant')) {
            $this->isTenant = true;
        } elseif ($this->option('central')) {
            $this->isTenant = false;
        } else {
            $dbType = select(
                label: 'Type de base de données',
                options: [
                    'tenant' => 'Tenant (base isolée par tenant) - Recommandé',
                    'central' => 'Centrale (base partagée mysql)',
                ],
                default: 'tenant'
            );
            $this->isTenant = $dbType === 'tenant';
        }

        // Controllers à créer
        $controllersOption = $this->option('controllers');
        if (! empty($controllersOption) && $controllersOption !== []) {
            // Flatten and parse controllers (handle both --controllers=admin --controllers=frontend and --controllers=admin,frontend)
            $this->controllers = [];
            foreach ((array) $controllersOption as $item) {
                foreach (explode(',', $item) as $controller) {
                    $controller = trim($controller);
                    if ($controller && in_array($controller, ['admin', 'frontend', 'superadmin'])) {
                        $this->controllers[] = $controller;
                    }
                }
            }
            $this->controllers = array_unique($this->controllers);
        }

        if (empty($this->controllers)) {
            $this->controllers = multiselect(
                label: 'Controllers à créer',
                options: [
                    'admin' => 'Admin - Gestion backoffice tenant',
                    'frontend' => 'Frontend - Application utilisateurs tenant',
                    'superadmin' => 'Superadmin - Gestion centrale',
                ],
                default: $this->isTenant ? ['admin'] : ['superadmin'],
                required: true
            );
        }

        // Champs du model
        $fieldsOption = $this->option('fields');
        if ($fieldsOption) {
            $this->fields = $this->parseFields($fieldsOption);
        } else {
            $fieldsInput = text(
                label: 'Champs du model (optionnel)',
                placeholder: 'name:string,description:text:nullable,price:decimal:10,2,is_active:boolean:default:true',
                hint: 'Format: nom:type[:options]. Laissez vide pour les champs par défaut.'
            );
            $this->fields = $fieldsInput ? $this->parseFields($fieldsInput) : $this->getDefaultFields();
        }

        // Options supplémentaires
        $this->withFactory = $this->option('with-factory') || confirm('Créer une Factory ?', true);
        $this->withSeeder = $this->option('with-seeder') || confirm('Créer un Seeder ?', true);
        $this->withTests = $this->option('with-tests') || confirm('Créer les Tests ?', false);
    }

    /**
     * Parse les champs depuis une chaîne
     */
    protected function parseFields(string $fieldsString): array
    {
        $fields = [];
        $parts = explode(',', $fieldsString);

        foreach ($parts as $part) {
            $segments = explode(':', trim($part));
            $name = $segments[0] ?? '';
            $type = $segments[1] ?? 'string';

            if (empty($name)) {
                continue;
            }

            $field = [
                'name' => $name,
                'type' => $type,
                'nullable' => in_array('nullable', $segments),
                'unique' => in_array('unique', $segments),
                'default' => null,
            ];

            // Chercher les options spécifiques
            $foundDecimalParams = false;
            foreach ($segments as $i => $segment) {
                if ($segment === 'default' && isset($segments[$i + 1])) {
                    $field['default'] = $segments[$i + 1];
                }
                if ($type === 'decimal' && is_numeric($segment) && ! $foundDecimalParams) {
                    $field['precision'] = (int) $segment;
                    if (isset($segments[$i + 1]) && is_numeric($segments[$i + 1])) {
                        $field['scale'] = (int) $segments[$i + 1];
                    }
                    $foundDecimalParams = true;
                }
                if ($type === 'enum' && $i > 1 && ! in_array($segment, ['nullable', 'unique', 'default'])) {
                    $field['values'][] = $segment;
                }
            }

            $fields[] = $field;
        }

        return $fields;
    }

    /**
     * Retourne les champs par défaut
     */
    protected function getDefaultFields(): array
    {
        return [
            ['name' => 'name', 'type' => 'string', 'nullable' => false, 'unique' => false, 'default' => null],
            ['name' => 'description', 'type' => 'text', 'nullable' => true, 'unique' => false, 'default' => null],
            ['name' => 'is_active', 'type' => 'boolean', 'nullable' => false, 'unique' => false, 'default' => 'true'],
        ];
    }

    /**
     * Affiche le résumé de la configuration
     */
    protected function displaySummary(): void
    {
        $this->info('');
        $this->info('┌─────────────────────────────────────────────────────────────┐');
        $this->info('│  📋 Résumé de la configuration                              │');
        $this->info('├─────────────────────────────────────────────────────────────┤');
        $this->line("│  Module      : <fg=cyan>{$this->moduleName}</>");
        $this->line("│  Model       : <fg=cyan>{$this->modelName}</>");
        $this->line("│  Table       : <fg=cyan>{$this->tableName}</>");
        $this->line('│  Base        : <fg=cyan>'.($this->isTenant ? 'Tenant (isolée)' : 'Centrale (mysql)').'</>');
        $this->line('│  Controllers : <fg=cyan>'.implode(', ', $this->controllers).'</>');
        $this->line('│  Factory     : <fg=cyan>'.($this->withFactory ? 'Oui' : 'Non').'</>');
        $this->line('│  Seeder      : <fg=cyan>'.($this->withSeeder ? 'Oui' : 'Non').'</>');
        $this->line('│  Tests       : <fg=cyan>'.($this->withTests ? 'Oui' : 'Non').'</>');
        $this->info('├─────────────────────────────────────────────────────────────┤');
        $this->info('│  Champs :                                                   │');

        foreach ($this->fields as $field) {
            $options = [];
            if ($field['nullable']) {
                $options[] = 'nullable';
            }
            if ($field['unique']) {
                $options[] = 'unique';
            }
            if ($field['default'] !== null) {
                $options[] = "default:{$field['default']}";
            }
            $optionsStr = $options ? ' ('.implode(', ', $options).')' : '';
            $this->line("│    - <fg=yellow>{$field['name']}</> : {$field['type']}{$optionsStr}");
        }

        $this->info('└─────────────────────────────────────────────────────────────┘');
        $this->info('');
    }

    /**
     * Crée le module de base avec module:make
     */
    protected function createModule(): void
    {
        $this->task('Création du module de base', function () {
            // Supprimer si existe et force
            if (File::isDirectory($this->modulePath) && $this->option('force')) {
                File::deleteDirectory($this->modulePath);
            }

            $this->call('module:make', [
                'name' => [$this->moduleName],
                '--no-interaction' => true,
            ]);

            return true;
        });
    }

    /**
     * Crée les Service Providers
     */
    protected function createServiceProviders(): void
    {
        $this->task('Création des Service Providers', function () {
            // ServiceProvider principal
            $serviceProvider = $this->getStub('service-provider');
            $this->writeFile("Providers/{$this->moduleName}ServiceProvider.php", $serviceProvider);

            // RouteServiceProvider
            $routeServiceProvider = $this->getStub('route-service-provider');
            $this->writeFile('Providers/RouteServiceProvider.php', $routeServiceProvider);

            return true;
        });
    }

    /**
     * Crée le fichier de configuration
     */
    protected function createConfig(): void
    {
        $this->task('Création de la configuration', function () {
            $config = $this->getStub('config');
            $this->writeFile('Config/config.php', $config);

            // Mettre à jour module.json
            $moduleJson = $this->getStub('module-json');
            $this->writeFile('module.json', $moduleJson);

            return true;
        });
    }

    /**
     * Crée le model
     */
    protected function createModel(): void
    {
        $this->task('Création du Model', function () {
            $model = $this->getStub('model');
            $this->writeFile("Entities/{$this->modelName}.php", $model);

            return true;
        });
    }

    /**
     * Crée la migration
     */
    protected function createMigration(): void
    {
        $this->task('Création de la Migration', function () {
            $migration = $this->getStub('migration');
            $timestamp = date('Y_m_d_His');
            $migrationName = "{$timestamp}_create_{$this->tableName}_table.php";

            if ($this->isTenant) {
                File::ensureDirectoryExists("{$this->modulePath}/Database/Migrations/tenant");
                $this->writeFile("Database/Migrations/tenant/{$migrationName}", $migration);
            } else {
                $this->writeFile("Database/Migrations/{$migrationName}", $migration);
            }

            return true;
        });
    }

    /**
     * Crée les Form Requests
     */
    protected function createFormRequests(): void
    {
        $this->task('Création des Form Requests', function () {
            File::ensureDirectoryExists("{$this->modulePath}/Http/Requests");

            $storeRequest = $this->getStub('store-request');
            $this->writeFile("Http/Requests/Store{$this->modelName}Request.php", $storeRequest);

            $updateRequest = $this->getStub('update-request');
            $this->writeFile("Http/Requests/Update{$this->modelName}Request.php", $updateRequest);

            return true;
        });
    }

    /**
     * Crée l'API Resource
     */
    protected function createResource(): void
    {
        $this->task('Création de l\'API Resource', function () {
            File::ensureDirectoryExists("{$this->modulePath}/Http/Resources");

            $resource = $this->getStub('resource');
            $this->writeFile("Http/Resources/{$this->modelName}Resource.php", $resource);

            return true;
        });
    }

    /**
     * Crée les Controllers
     */
    protected function createControllers(): void
    {
        foreach ($this->controllers as $type) {
            $typePascal = ucfirst($type);
            $this->task("Création du Controller {$typePascal}", function () use ($type, $typePascal) {
                File::ensureDirectoryExists("{$this->modulePath}/Http/Controllers/{$typePascal}");

                $controller = $this->getStub('controller', ['type' => $type]);
                $this->writeFile("Http/Controllers/{$typePascal}/{$this->modelName}Controller.php", $controller);

                return true;
            });
        }
    }

    /**
     * Crée les fichiers de routes
     */
    protected function createRoutes(): void
    {
        $this->task('Création des Routes', function () {
            foreach (['admin', 'frontend', 'superadmin'] as $type) {
                if (in_array($type, $this->controllers)) {
                    $routes = $this->getStub('routes', ['type' => $type]);
                } else {
                    $routes = $this->getStub('routes-empty', ['type' => $type]);
                }
                $this->writeFile("Routes/{$type}.php", $routes);
            }

            return true;
        });
    }

    /**
     * Crée la Factory
     */
    protected function createFactory(): void
    {
        $this->task('Création de la Factory', function () {
            File::ensureDirectoryExists("{$this->modulePath}/Database/Factories");

            $factory = $this->getStub('factory');
            $this->writeFile("Database/Factories/{$this->modelName}Factory.php", $factory);

            return true;
        });
    }

    /**
     * Crée le Seeder
     */
    protected function createSeeder(): void
    {
        $this->task('Création du Seeder', function () {
            File::ensureDirectoryExists("{$this->modulePath}/Database/Seeders");

            $seeder = $this->getStub('seeder');
            $this->writeFile("Database/Seeders/{$this->moduleName}Seeder.php", $seeder);

            return true;
        });
    }

    /**
     * Crée les Tests
     */
    protected function createTests(): void
    {
        $this->task('Création des Tests', function () {
            File::ensureDirectoryExists("{$this->modulePath}/Tests/Feature");

            $test = $this->getStub('test');
            $this->writeFile("Tests/Feature/{$this->modelName}Test.php", $test);

            return true;
        });
    }

    /**
     * Formate le code avec Pint
     */
    protected function formatCode(): void
    {
        $this->task('Formatage du code avec Pint', function () {
            exec("vendor/bin/pint {$this->modulePath} --quiet 2>&1");

            return true;
        });
    }

    /**
     * Affiche les prochaines étapes
     */
    protected function displayNextSteps(): void
    {
        $this->info('📌 Prochaines étapes :');
        $this->info('');
        $this->line("   1. Vérifier les fichiers générés dans <fg=cyan>modules/{$this->moduleName}</>");
        $this->line('   2. Ajuster les champs dans la migration si nécessaire');

        if ($this->isTenant) {
            $this->line('   3. Exécuter les migrations tenant :');
            $this->line('      <fg=yellow>php artisan tenants:migrate</>');
        } else {
            $this->line('   3. Exécuter les migrations :');
            $this->line("      <fg=yellow>php artisan module:migrate {$this->moduleName}</>");
        }

        if ($this->withSeeder) {
            $this->line('   4. Exécuter le seeder :');
            $this->line("      <fg=yellow>php artisan module:seed {$this->moduleName}</>");
        }

        $this->line('   5. Tester les endpoints API');
        $this->info('');

        $this->info('📚 Endpoints créés :');

        foreach ($this->controllers as $type) {
            $prefix = $type === 'superadmin' ? 'superadmin' : $type;
            $this->line("   <fg=green>GET</>    /api/{$prefix}/{$this->moduleNameLower}");
            $this->line("   <fg=blue>POST</>   /api/{$prefix}/{$this->moduleNameLower}");
            $this->line("   <fg=green>GET</>    /api/{$prefix}/{$this->moduleNameLower}/{id}");
            $this->line("   <fg=yellow>PUT</>    /api/{$prefix}/{$this->moduleNameLower}/{id}");
            $this->line("   <fg=red>DELETE</> /api/{$prefix}/{$this->moduleNameLower}/{id}");
        }

        $this->info('');
    }

    /**
     * Écrit un fichier dans le module
     */
    protected function writeFile(string $path, string $content): void
    {
        $fullPath = "{$this->modulePath}/{$path}";
        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $content);
    }

    /**
     * Affiche une tâche avec son statut
     */
    protected function task(string $title, callable $task): void
    {
        $this->output->write("   ▸ {$title}... ");

        try {
            $result = $task();
            if ($result) {
                $this->output->writeln('<fg=green>✓</>');
            } else {
                $this->output->writeln('<fg=red>✗</>');
            }
        } catch (\Exception $e) {
            $this->output->writeln('<fg=red>✗</>');
            $this->error("     Erreur: {$e->getMessage()}");
        }
    }

    /**
     * Récupère et remplit un template stub
     */
    protected function getStub(string $name, array $extra = []): string
    {
        $stubPath = base_path("stubs/module/{$name}.stub");

        if (! File::exists($stubPath)) {
            return $this->getInlineStub($name, $extra);
        }

        $content = File::get($stubPath);

        return $this->replacePlaceholders($content, $extra);
    }

    /**
     * Remplace les placeholders dans le contenu
     */
    protected function replacePlaceholders(string $content, array $extra = []): string
    {
        $replacements = [
            '{{moduleName}}' => $this->moduleName,
            '{{moduleNameLower}}' => $this->moduleNameLower,
            '{{modelName}}' => $this->modelName,
            '{{modelNameLower}}' => Str::lower($this->modelName),
            '{{modelNamePlural}}' => Str::plural($this->modelName),
            '{{tableName}}' => $this->tableName,
            '{{connection}}' => $this->isTenant ? 'tenant' : 'mysql',
            '{{fillable}}' => $this->generateFillable(),
            '{{casts}}' => $this->generateCasts(),
            '{{migrationFields}}' => $this->generateMigrationFields(),
            '{{validationRules}}' => $this->generateValidationRules(),
            '{{validationRulesUpdate}}' => $this->generateValidationRules(true),
            '{{resourceFields}}' => $this->generateResourceFields(),
            '{{factoryFields}}' => $this->generateFactoryFields(),
        ];

        // Ajouter les extras
        foreach ($extra as $key => $value) {
            $replacements["{{$key}}"] = $value;
        }

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Génère la liste fillable pour le model
     */
    protected function generateFillable(): string
    {
        $names = array_map(fn ($f) => "'{$f['name']}'", $this->fields);

        return implode(",\n        ", $names);
    }

    /**
     * Génère les casts pour le model
     */
    protected function generateCasts(): string
    {
        $casts = [];

        foreach ($this->fields as $field) {
            $cast = match ($field['type']) {
                'boolean' => 'boolean',
                'integer', 'bigInteger' => 'integer',
                'decimal', 'float', 'double' => 'float',
                'json', 'array' => 'array',
                'date' => 'date',
                'datetime', 'timestamp' => 'datetime',
                default => null
            };

            if ($cast) {
                $casts[] = "'{$field['name']}' => '{$cast}'";
            }
        }

        $casts[] = "'created_at' => 'datetime'";
        $casts[] = "'updated_at' => 'datetime'";
        $casts[] = "'deleted_at' => 'datetime'";

        return implode(",\n            ", $casts);
    }

    /**
     * Génère les champs de migration
     */
    protected function generateMigrationFields(): string
    {
        $lines = [];

        foreach ($this->fields as $field) {
            $line = match ($field['type']) {
                'string' => "\$table->string('{$field['name']}')",
                'text' => "\$table->text('{$field['name']}')",
                'boolean' => "\$table->boolean('{$field['name']}')",
                'integer' => "\$table->integer('{$field['name']}')",
                'bigInteger' => "\$table->bigInteger('{$field['name']}')",
                'decimal' => "\$table->decimal('{$field['name']}', ".($field['precision'] ?? 10).', '.($field['scale'] ?? 2).')',
                'float' => "\$table->float('{$field['name']}')",
                'date' => "\$table->date('{$field['name']}')",
                'datetime' => "\$table->dateTime('{$field['name']}')",
                'timestamp' => "\$table->timestamp('{$field['name']}')",
                'json' => "\$table->json('{$field['name']}')",
                'enum' => "\$table->enum('{$field['name']}', ['".implode("', '", $field['values'] ?? ['option1', 'option2'])."'])",
                default => "\$table->string('{$field['name']}')"
            };

            if ($field['nullable']) {
                $line .= '->nullable()';
            }

            if ($field['unique']) {
                $line .= '->unique()';
            }

            if ($field['default'] !== null) {
                $defaultValue = match (true) {
                    $field['default'] === 'true' => 'true',
                    $field['default'] === 'false' => 'false',
                    is_numeric($field['default']) => $field['default'],
                    default => "'{$field['default']}'"
                };
                $line .= "->default({$defaultValue})";
            }

            $lines[] = $line.';';
        }

        return implode("\n            ", $lines);
    }

    /**
     * Génère les règles de validation
     */
    protected function generateValidationRules(bool $isUpdate = false): string
    {
        $rules = [];

        foreach ($this->fields as $field) {
            $fieldRules = [];

            if ($isUpdate) {
                $fieldRules[] = 'sometimes';
            } elseif (! $field['nullable']) {
                $fieldRules[] = 'required';
            }

            $typeRule = match ($field['type']) {
                'string' => 'string', 'max:255',
                'text' => 'string',
                'boolean' => 'boolean',
                'integer', 'bigInteger' => 'integer',
                'decimal', 'float', 'double' => 'numeric',
                'date' => 'date',
                'datetime', 'timestamp' => 'date',
                'json', 'array' => 'array',
                'enum' => "in:'".implode("','", $field['values'] ?? [])."'",
                default => 'string'
            };

            if (is_array($typeRule)) {
                $fieldRules = array_merge($fieldRules, $typeRule);
            } else {
                $fieldRules[] = $typeRule;
            }

            if ($field['nullable']) {
                $fieldRules[] = 'nullable';
            }

            if ($field['unique']) {
                $fieldRules[] = "unique:{$this->tableName},{$field['name']}";
            }

            $rules[] = "'{$field['name']}' => ['".implode("', '", $fieldRules)."']";
        }

        return implode(",\n            ", $rules);
    }

    /**
     * Génère les champs de la Resource
     */
    protected function generateResourceFields(): string
    {
        $lines = ["'id' => \$this->id"];

        foreach ($this->fields as $field) {
            $lines[] = "'{$field['name']}' => \$this->{$field['name']}";
        }

        return implode(",\n            ", $lines);
    }

    /**
     * Génère les champs de la Factory
     */
    protected function generateFactoryFields(): string
    {
        $lines = [];

        foreach ($this->fields as $field) {
            $faker = match ($field['type']) {
                'string' => $field['name'] === 'name' ? 'fake()->word()' : 'fake()->sentence(3)',
                'text' => 'fake()->paragraph()',
                'boolean' => 'fake()->boolean(80)',
                'integer', 'bigInteger' => 'fake()->randomNumber(3)',
                'decimal', 'float', 'double' => 'fake()->randomFloat(2, 0, 1000)',
                'date' => 'fake()->date()',
                'datetime', 'timestamp' => 'fake()->dateTime()',
                'email' => 'fake()->unique()->safeEmail()',
                'enum' => "fake()->randomElement(['".implode("', '", $field['values'] ?? ['option1', 'option2'])."'])",
                default => 'fake()->word()'
            };

            // Cas spéciaux basés sur le nom du champ
            if (str_contains($field['name'], 'email')) {
                $faker = 'fake()->unique()->safeEmail()';
            } elseif (str_contains($field['name'], 'name')) {
                $faker = 'fake()->word()';
            } elseif (str_contains($field['name'], 'description')) {
                $faker = 'fake()->sentence()';
            } elseif (str_contains($field['name'], 'price') || str_contains($field['name'], 'amount')) {
                $faker = 'fake()->randomFloat(2, 10, 1000)';
            } elseif (str_contains($field['name'], 'phone')) {
                $faker = 'fake()->phoneNumber()';
            } elseif (str_contains($field['name'], 'address')) {
                $faker = 'fake()->address()';
            } elseif (str_contains($field['name'], 'city')) {
                $faker = 'fake()->city()';
            } elseif (str_contains($field['name'], 'country')) {
                $faker = 'fake()->country()';
            }

            $lines[] = "'{$field['name']}' => {$faker}";
        }

        return implode(",\n            ", $lines);
    }

    /**
     * Retourne les stubs inline (si pas de fichier stub)
     */
    protected function getInlineStub(string $name, array $extra = []): string
    {
        $stubs = [
            'module-json' => <<<'STUB'
{
    "name": "{{moduleName}}",
    "alias": "{{moduleNameLower}}",
    "description": "Module {{moduleName}} pour le CRM",
    "keywords": [],
    "priority": 0,
    "providers": [
        "Modules\\{{moduleName}}\\Providers\\{{moduleName}}ServiceProvider"
    ],
    "files": []
}
STUB,

            'config' => <<<'STUB'
<?php

return [
    'name' => '{{moduleName}}',
];
STUB,

            'service-provider' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Providers;

use Illuminate\Support\ServiceProvider;

class {{moduleName}}ServiceProvider extends ServiceProvider
{
    protected $moduleName = '{{moduleName}}';

    protected $moduleNameLower = '{{moduleNameLower}}';

    /**
     * Boot the application events.
     */
    public function boot(): void
    {
        $this->registerConfig();
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/admin.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/frontend.php'));
        $this->loadRoutesFrom(module_path($this->moduleName, 'Routes/superadmin.php'));
    }

    /**
     * Register the service provider.
     */
    public function register(): void
    {
        $this->app->register(RouteServiceProvider::class);
    }

    /**
     * Register config.
     */
    protected function registerConfig(): void
    {
        $this->publishes([
            module_path($this->moduleName, 'Config/config.php') => config_path($this->moduleNameLower . '.php'),
        ], 'config');

        $this->mergeConfigFrom(
            module_path($this->moduleName, 'Config/config.php'),
            $this->moduleNameLower
        );
    }

    /**
     * Get the services provided by the provider.
     */
    public function provides(): array
    {
        return [];
    }
}
STUB,

            'route-service-provider' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Providers;

use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Route;

class RouteServiceProvider extends ServiceProvider
{
    /**
     * The module namespace to assume when generating URLs to actions.
     */
    protected $moduleNamespace = 'Modules\{{moduleName}}\Http\Controllers';

    /**
     * Called before routes are registered.
     */
    public function boot(): void
    {
        parent::boot();
    }

    /**
     * Define the routes for the application.
     */
    public function map(): void
    {
        $this->mapApiRoutes();
    }

    /**
     * Define the "api" routes for the application.
     */
    protected function mapApiRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('{{moduleName}}', '/Routes/admin.php'));

        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('{{moduleName}}', '/Routes/frontend.php'));

        Route::prefix('api')
            ->middleware('api')
            ->namespace($this->moduleNamespace)
            ->group(module_path('{{moduleName}}', '/Routes/superadmin.php'));
    }
}
STUB,

            'model' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Entities;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class {{modelName}} extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The database connection.
     */
    protected $connection = '{{connection}}';

    /**
     * The table associated with the model.
     */
    protected $table = '{{tableName}}';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        {{fillable}},
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            {{casts}},
        ];
    }

    /**
     * Scope to get only active records.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Check if record is active.
     */
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }
}
STUB,

            'migration' => <<<'STUB'
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
        Schema::create('{{tableName}}', function (Blueprint $table) {
            $table->id();
            {{migrationFields}}
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{{tableName}}');
    }
};
STUB,

            'store-request' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Store{{modelName}}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            {{validationRules}},
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Le nom est obligatoire.',
        ];
    }
}
STUB,

            'update-request' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class Update{{modelName}}Request extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            {{validationRulesUpdate}},
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [];
    }
}
STUB,

            'resource' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class {{modelName}}Resource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray($request): array
    {
        return [
            {{resourceFields}},
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'deleted_at' => $this->deleted_at?->toIso8601String(),
        ];
    }
}
STUB,

            'factory' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\{{moduleName}}\Entities\{{modelName}};

class {{modelName}}Factory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     */
    protected $model = {{modelName}}::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            {{factoryFields}},
        ];
    }

    /**
     * Indicate that the model is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the model is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }
}
STUB,

            'seeder' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\{{moduleName}}\Entities\{{modelName}};

class {{moduleName}}Seeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'name' => 'Exemple 1',
                'description' => 'Description de l\'exemple 1',
                'is_active' => true,
            ],
            [
                'name' => 'Exemple 2',
                'description' => 'Description de l\'exemple 2',
                'is_active' => true,
            ],
        ];

        foreach ($items as $item) {
            {{modelName}}::updateOrCreate(
                ['name' => $item['name']],
                $item
            );
        }

        $this->command->info('{{moduleName}} seeded successfully.');
    }
}
STUB,

            'test' => <<<'STUB'
<?php

namespace Modules\{{moduleName}}\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\{{moduleName}}\Entities\{{modelName}};
use Tests\TestCase;

class {{modelName}}Test extends TestCase
{
    use RefreshDatabase;

    public function testCanList{{modelNamePlural}}(): void
    {
        {{modelName}}::factory()->count(3)->create();

        $response = $this->getJson('/api/admin/{{moduleNameLower}}');

        $response->assertStatus(200);
    }

    public function testCanCreate{{modelName}}(): void
    {
        $data = [
            'name' => 'Test {{modelName}}',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/admin/{{moduleNameLower}}', $data);

        $response->assertStatus(201);
        $this->assertDatabaseHas('{{tableName}}', ['name' => 'Test {{modelName}}']);
    }

    public function testCanUpdate{{modelName}}(): void
    {
        $model = {{modelName}}::factory()->create();

        $response = $this->putJson("/api/admin/{{moduleNameLower}}/{$model->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('{{tableName}}', [
            'id' => $model->id,
            'name' => 'Updated Name',
        ]);
    }

    public function testCanDelete{{modelName}}(): void
    {
        $model = {{modelName}}::factory()->create();

        $response = $this->deleteJson("/api/admin/{{moduleNameLower}}/{$model->id}");

        $response->assertStatus(200);
        $this->assertSoftDeleted('{{tableName}}', ['id' => $model->id]);
    }
}
STUB,
        ];

        // Controller selon le type
        $type = $extra['type'] ?? 'admin';
        $typePascal = ucfirst($type);

        $middleware = match ($type) {
            'admin' => "['tenant', 'tenant.auth']",
            'frontend' => "['tenant', 'tenant.auth']",
            'superadmin' => "['superadmin.auth']",
            default => "['tenant', 'tenant.auth']"
        };

        $stubs['controller'] = <<<STUB
<?php

namespace Modules\{{moduleName}}\Http\Controllers\\{$typePascal};

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Modules\{{moduleName}}\Entities\{{modelName}};
use Modules\{{moduleName}}\Http\Requests\Store{{modelName}}Request;
use Modules\{{moduleName}}\Http\Requests\Update{{modelName}}Request;
use Modules\{{moduleName}}\Http\Resources\{{modelName}}Resource;

class {{modelName}}Controller extends Controller
{
    /**
     * Display a listing of the resources.
     */
    public function index(Request \$request): AnonymousResourceCollection
    {
        \$perPage = \$request->input('per_page', 15);
        \$search = \$request->input('search');
        \$isActive = \$request->input('is_active');

        \$query = {{modelName}}::query();

        if (\$search) {
            \$query->where('name', 'like', "%{\$search}%");
        }

        if (\$isActive !== null) {
            \$query->where('is_active', \$isActive);
        }

        \$items = \$query->latest()->paginate(\$perPage);

        return {{modelName}}Resource::collection(\$items);
    }

    /**
     * Store a newly created resource.
     */
    public function store(Store{{modelName}}Request \$request): JsonResponse
    {
        \$data = \$request->validated();
        \$data['is_active'] = \$data['is_active'] ?? true;

        \$item = {{modelName}}::create(\$data);

        return response()->json([
            'message' => 'Créé avec succès.',
            'data' => new {{modelName}}Resource(\$item),
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string \$id): JsonResponse
    {
        \$item = {{modelName}}::findOrFail(\$id);

        return response()->json([
            'data' => new {{modelName}}Resource(\$item),
        ]);
    }

    /**
     * Update the specified resource.
     */
    public function update(Update{{modelName}}Request \$request, string \$id): JsonResponse
    {
        \$item = {{modelName}}::findOrFail(\$id);
        \$item->update(\$request->validated());

        return response()->json([
            'message' => 'Modifié avec succès.',
            'data' => new {{modelName}}Resource(\$item),
        ]);
    }

    /**
     * Remove the specified resource (soft delete).
     */
    public function destroy(string \$id): JsonResponse
    {
        \$item = {{modelName}}::findOrFail(\$id);
        \$item->delete();

        return response()->json([
            'message' => 'Supprimé avec succès.',
        ]);
    }

    /**
     * Restore a soft deleted resource.
     */
    public function restore(string \$id): JsonResponse
    {
        \$item = {{modelName}}::onlyTrashed()->findOrFail(\$id);
        \$item->restore();

        return response()->json([
            'message' => 'Restauré avec succès.',
            'data' => new {{modelName}}Resource(\$item),
        ]);
    }

    /**
     * Permanently delete a resource.
     */
    public function forceDelete(string \$id): JsonResponse
    {
        \$item = {{modelName}}::withTrashed()->findOrFail(\$id);
        \$item->forceDelete();

        return response()->json([
            'message' => 'Supprimé définitivement.',
        ]);
    }
}
STUB;

        // Routes selon le type
        $stubs['routes'] = match ($type) {
            'admin' => <<<'STUB'
<?php

use Illuminate\Support\Facades\Route;
use Modules\{{moduleName}}\Http\Controllers\Admin\{{modelName}}Controller;

// Routes protégées (tenant + auth)
Route::prefix('admin')->middleware(['tenant', 'tenant.auth'])->group(function () {
    Route::prefix('{{moduleNameLower}}')->group(function () {
        Route::get('/', [{{modelName}}Controller::class, 'index']);
        Route::post('/', [{{modelName}}Controller::class, 'store']);
        Route::get('/{id}', [{{modelName}}Controller::class, 'show']);
        Route::put('/{id}', [{{modelName}}Controller::class, 'update']);
        Route::delete('/{id}', [{{modelName}}Controller::class, 'destroy']);

        // Restore & Force Delete
        Route::post('/{id}/restore', [{{modelName}}Controller::class, 'restore']);
        Route::delete('/{id}/force', [{{modelName}}Controller::class, 'forceDelete']);
    });
});
STUB,
            'frontend' => <<<'STUB'
<?php

use Illuminate\Support\Facades\Route;
use Modules\{{moduleName}}\Http\Controllers\Frontend\{{modelName}}Controller;

// Routes protégées (tenant + auth)
Route::prefix('frontend')->middleware(['tenant', 'tenant.auth'])->group(function () {
    Route::prefix('{{moduleNameLower}}')->group(function () {
        Route::get('/', [{{modelName}}Controller::class, 'index']);
        Route::post('/', [{{modelName}}Controller::class, 'store']);
        Route::get('/{id}', [{{modelName}}Controller::class, 'show']);
        Route::put('/{id}', [{{modelName}}Controller::class, 'update']);
        Route::delete('/{id}', [{{modelName}}Controller::class, 'destroy']);

        // Restore & Force Delete
        Route::post('/{id}/restore', [{{modelName}}Controller::class, 'restore']);
        Route::delete('/{id}/force', [{{modelName}}Controller::class, 'forceDelete']);
    });
});
STUB,
            'superadmin' => <<<'STUB'
<?php

use Illuminate\Support\Facades\Route;
use Modules\{{moduleName}}\Http\Controllers\Superadmin\{{modelName}}Controller;

// Routes protégées (superadmin auth)
Route::prefix('superadmin')->middleware(['superadmin.auth'])->group(function () {
    Route::prefix('{{moduleNameLower}}')->group(function () {
        Route::get('/', [{{modelName}}Controller::class, 'index']);
        Route::post('/', [{{modelName}}Controller::class, 'store']);
        Route::get('/{id}', [{{modelName}}Controller::class, 'show']);
        Route::put('/{id}', [{{modelName}}Controller::class, 'update']);
        Route::delete('/{id}', [{{modelName}}Controller::class, 'destroy']);

        // Restore & Force Delete
        Route::post('/{id}/restore', [{{modelName}}Controller::class, 'restore']);
        Route::delete('/{id}/force', [{{modelName}}Controller::class, 'forceDelete']);
    });
});
STUB,
            default => ''
        };

        $stubs['routes-empty'] = <<<'STUB'
<?php

use Illuminate\Support\Facades\Route;

// Routes {{type}} pour {{moduleName}}
// Ajoutez vos routes ici
STUB;

        $content = $stubs[$name] ?? '';

        return $this->replacePlaceholders($content, $extra);
    }
}
