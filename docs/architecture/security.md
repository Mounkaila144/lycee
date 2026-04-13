# Sécurité

[← Retour à l'index](./index.md)

---

## Authentification et Autorisation

### 1. Authentification Multi-niveaux

**Niveaux d'Accès** :

| Niveau | Guard | Connection BD | Domaine d'Action |
|--------|-------|---------------|------------------|
| **Superadmin** | `sanctum` | `mysql` (centrale) | Gestion tenants |
| **Admin Tenant** | `tenant` | `tenant_{id}` | Gestion établissement |
| **Frontend Tenant** | `tenant` | `tenant_{id}` | Enseignants, Élèves, Parents, Surveillant Général, Comptable |

### 2. Laravel Sanctum

**Token-based Authentication** :

```php
// Login
public function login(Request $request)
{
    $credentials = $request->validate([
        'email' => 'required|email',
        'password' => 'required',
    ]);

    if (!Auth::attempt($credentials)) {
        return response()->json(['message' => 'Identifiants invalides'], 401);
    }

    $user = Auth::user();
    $token = $user->createToken('auth-token')->plainTextToken;

    return response()->json([
        'token' => $token,
        'user' => new UserResource($user),
    ]);
}

// Logout
public function logout(Request $request)
{
    $request->user()->currentAccessToken()->delete();

    return response()->json(['message' => 'Déconnexion réussie']);
}
```

**Utilisation Frontend** :

```typescript
// Stocker token
localStorage.setItem('auth_token', token);

// Axios interceptor
axios.interceptors.request.use((config) => {
  const token = localStorage.getItem('auth_token');
  if (token) {
    config.headers.Authorization = `Bearer ${token}`;
  }
  return config;
});
```

### 3. Spatie Permissions

**Rôles et Permissions** :

```php
// Définir rôles (8 rôles)
$superadmin = Role::create(['name' => 'SuperAdmin']);
$admin = Role::create(['name' => 'Admin']);
$censeur = Role::create(['name' => 'Censeur']);
$surveillantGeneral = Role::create(['name' => 'Surveillant Général']);
$enseignant = Role::create(['name' => 'Enseignant']);
$comptable = Role::create(['name' => 'Comptable']);
$eleve = Role::create(['name' => 'Élève']);
$parent = Role::create(['name' => 'Parent']);

// Définir permissions
$manageStudents = Permission::create(['name' => 'manage-students']);
$viewGrades = Permission::create(['name' => 'view-grades']);
$enterGrades = Permission::create(['name' => 'enter-grades']);
$manageDiscipline = Permission::create(['name' => 'manage-discipline']);
$manageAttendance = Permission::create(['name' => 'manage-attendance']);
$manageFinance = Permission::create(['name' => 'manage-finance']);
$viewOwnGrades = Permission::create(['name' => 'view-own-grades']);
$viewChildrenData = Permission::create(['name' => 'view-children-data']);

// Attribuer permissions aux rôles
$admin->givePermissionTo([
    'manage-students', 'view-grades', 'enter-grades',
    'manage-discipline', 'manage-attendance', 'manage-finance',
]);
$censeur->givePermissionTo([
    'view-grades', 'enter-grades', 'manage-discipline', 'manage-attendance',
]);
$surveillantGeneral->givePermissionTo([
    'manage-discipline', 'manage-attendance', 'view-grades',
]);
$enseignant->givePermissionTo(['view-grades', 'enter-grades', 'manage-attendance']);
$comptable->givePermissionTo(['manage-finance', 'view-grades']);
$eleve->givePermissionTo(['view-own-grades']);
$parent->givePermissionTo(['view-children-data']);

// Attribuer rôle à utilisateur
$user->assignRole('Enseignant');
```

**Vérification Permissions** :

```php
// Dans Controller
public function store(StoreStudentRequest $request)
{
    $this->authorize('manage-students');

    // Logique
}

// Dans Policy
public function update(User $user, Student $student)
{
    return $user->hasPermissionTo('manage-students');
}
```

---

## Protection des Données de Mineurs

### 1. Conformité RGPD / Loi Nigérienne sur la Protection des Données

**Principes Appliqués** :
- **Minimisation** : Ne collecter que les données strictement nécessaires
- **Consentement parental** : Les comptes élèves mineurs sont créés par l'administration avec accord parent
- **Droit à l'effacement** : SoftDeletes + capacité de purge sur demande
- **Limitation d'accès** : Données d'un élève accessibles uniquement par ses enseignants, ses parents, et l'administration

**Données Sensibles des Mineurs** :
- Informations médicales (allergies, handicap) : accès restreint Admin + Surveillant Général
- Dossier disciplinaire : accès restreint Admin + Censeur + Surveillant Général + Parents de l'élève
- Notes : accessibles par l'élève, ses parents, ses enseignants, et l'administration
- Photos : stockage sécurisé, accès restreint

### 2. Isolation des Données Parent ↔ Enfant

**Règles Strictes** :
```php
// Un parent ne peut voir que les données de SES enfants
public function viewGrades(User $user, Student $student): bool
{
    if ($user->hasRole('Parent')) {
        return $user->parent->children()->where('student_id', $student->id)->exists();
    }

    return false;
}

// Un enseignant ne peut voir que les notes de SES classes
public function viewClassGrades(User $user, ClassModel $class): bool
{
    if ($user->hasRole('Enseignant')) {
        return $user->teacherAssignments()
            ->where('class_id', $class->id)
            ->exists();
    }

    return false;
}
```

### 3. Restriction d'Âge sur les Comptes

```php
// Les élèves mineurs n'ont pas accès à certaines fonctionnalités
// Le compte élève est volontairement limité en lecture seule
// Pas d'export de données personnelles par l'élève mineur
```

---

## Isolation Multi-tenant

### 1. Garanties

- ✅ Bases de données strictement séparées par tenant (établissement)
- ✅ Impossibilité d'accéder aux données d'un autre établissement
- ✅ Middleware `tenant` sur toutes routes tenant
- ✅ Connexion `'tenant'` explicite dans tous les Models

**Vérification Automatique** :

```php
// Middleware tenant vérifie automatiquement
Route::middleware(['tenant', 'tenant.auth'])->group(function () {
    // Toutes les queries utilisent la BD tenant_{id}
});
```

### 2. Chiffrement Données Sensibles

**Données à Chiffrer** :
- Contacts d'urgence des élèves
- Numéros de téléphone des parents
- Documents confidentiels (bulletins, dossiers disciplinaires)

**Implémentation** :

```php
// Dans Model
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function emergencyPhone(): Attribute
{
    return Attribute::make(
        get: fn($value) => decrypt($value),
        set: fn($value) => encrypt($value),
    );
}
```

### 3. Hachage Mots de Passe

**Bcrypt automatique** :

```php
// Lors de la création utilisateur
User::create([
    'name' => 'Amadou Diallo',
    'email' => 'amadou@example.com',
    'password' => bcrypt('password'),  // OU Hash::make('password')
]);
```

---

## Validation et Sanitisation

### 1. Validation Stricte

**Form Requests** :

```php
public function rules(): array
{
    return [
        'email' => ['required', 'email', 'max:255'],
        'firstname' => ['required', 'string', 'max:255', 'regex:/^[a-zA-ZÀ-ÿ\s\-]+$/'],
        'lastname' => ['required', 'string', 'max:255', 'regex:/^[a-zA-ZÀ-ÿ\s\-]+$/'],
        'birthdate' => ['required', 'date', 'before:today'],
        'phone' => ['nullable', 'regex:/^[+]?[0-9\s\-\(\)]+$/'],
        'score' => ['required', 'numeric', 'min:0', 'max:20'],  // Note sur 20
    ];
}
```

### 2. SQL Injection Prevention

**Utilisation Eloquent ORM** :

```php
// ✅ BON - Eloquent avec bindings automatiques
Student::where('email', $email)->first();

// ✅ BON - Query Builder avec bindings
DB::table('students')->where('email', '=', $email)->get();

// ❌ DANGEREUX - Raw query sans bindings
DB::select("SELECT * FROM students WHERE email = '$email'");

// ✅ BON - Raw query avec bindings
DB::select("SELECT * FROM students WHERE email = ?", [$email]);
```

### 3. XSS Prevention

**Frontend React** :

```typescript
// ✅ BON - React échappe automatiquement
<div>{student.name}</div>

// ❌ DANGEREUX - dangerouslySetInnerHTML
<div dangerouslySetInnerHTML={{ __html: student.name }} />
```

---

## CSRF Protection

### Backend

```php
// CSRF vérifié automatiquement sur routes POST/PUT/DELETE
// Token inclus dans formulaires Blade
@csrf
```

### Frontend (API)

**Pas de CSRF pour API stateless** :

```php
// Dans bootstrap/app.php
->withMiddleware(function (Middleware $middleware) {
    $middleware->validateCsrfTokens(except: [
        'api/*',  // Exemption API
    ]);
})
```

---

## Rate Limiting

### Configuration

```php
// Dans bootstrap/app.php
RateLimiter::for('api', function (Request $request) {
    return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
});

// Routes avec rate limiting
Route::middleware(['throttle:api'])->group(function () {
    // Routes limitées à 60 req/min
});
```

### Endpoints Sensibles

```php
// Login - 5 tentatives / minute
Route::post('/login')->middleware('throttle:5,1');

// Import CSV - 10 tentatives / heure
Route::post('/students/import')->middleware('throttle:10,60');

// Génération bulletins en masse - 3 tentatives / heure
Route::post('/report-cards/generate-batch')->middleware('throttle:3,60');
```

---

## Sécurité des Fichiers

### 1. Upload Fichiers

**Validation** :

```php
public function rules(): array
{
    return [
        'photo' => [
            'required',
            'file',
            'image',
            'mimes:jpeg,png,jpg',
            'max:2048',  // 2MB max
        ],
        'justificatif_absence' => [
            'required',
            'file',
            'mimes:pdf,jpeg,png',
            'max:5120',  // 5MB max
        ],
    ];
}
```

**Stockage Sécurisé** :

```php
// Stockage hors dossier public
$path = $request->file('photo')->store('photos', 'tenant');

// Génération URL signée (temporaire)
$url = Storage::disk('tenant')->temporaryUrl('photos/eleve_123.jpg', now()->addMinutes(30));
```

### 2. Téléchargement Fichiers

**Vérification Propriété** :

```php
public function download(GeneratedDocument $document)
{
    $user = auth()->user();

    // Vérifier accès selon rôle
    if ($user->hasRole('Élève') && $document->student_id !== $user->student_id) {
        abort(403);
    }

    if ($user->hasRole('Parent')) {
        $childIds = $user->parent->children()->pluck('student_id');
        if (!$childIds->contains($document->student_id)) {
            abort(403);
        }
    }

    return Storage::disk('tenant')->download($document->document_pdf_path);
}
```

---

## Logging et Audit

### 1. Logs Sécurité

**Événements à Logger** :
- Tentatives de connexion (succès/échec)
- Modifications de notes (ancien score → nouveau score)
- Modifications de données sensibles (dossier disciplinaire, paiements)
- Changements de permissions
- Accès refusés (403)
- Génération de documents officiels (bulletins, attestations)

**Implémentation** :

```php
// Login échec
Log::warning('Login attempt failed', [
    'email' => $request->email,
    'ip' => $request->ip(),
    'user_agent' => $request->userAgent(),
]);

// Modification note (audit critique)
Log::info('Grade updated', [
    'tenant_id' => tenant('id'),
    'user_id' => auth()->id(),
    'student_id' => $grade->student_id,
    'evaluation_id' => $grade->evaluation_id,
    'old_score' => $grade->getOriginal('score'),
    'new_score' => $grade->score,
]);

// Génération bulletin
Log::info('Report card generated', [
    'tenant_id' => tenant('id'),
    'user_id' => auth()->id(),
    'class_id' => $class->id,
    'semester_id' => $semester->id,
    'count' => $studentCount,
]);
```

### 2. Audit Trail

**Tables d'Historique** :

```php
// Historique statut élève (inscrit, transféré, exclu, etc.)
StudentStatusHistory::create([
    'student_id' => $student->id,
    'old_status' => $student->getOriginal('status'),
    'new_status' => $student->status,
    'changed_by' => auth()->id(),
    'changed_at' => now(),
    'comment' => $request->comment,
]);
```

---

## Bonnes Pratiques Générales

### Backend

1. **Jamais d'env() en dehors de config/**
   ```php
   // ✅ BON
   config('app.name')

   // ❌ MAUVAIS
   env('APP_NAME')
   ```

2. **Toujours valider entrées utilisateur**

3. **Utiliser Policies pour autorisation complexe** (accès Parent ↔ Enfant, Enseignant ↔ Classe)

4. **SoftDeletes pour traçabilité** (obligatoire sur toutes les tables métier)

5. **HTTPS obligatoire en production**
   ```php
   // Force HTTPS
   if (!$request->secure() && app()->environment('production')) {
       return redirect()->secure($request->getRequestUri());
   }
   ```

### Frontend

1. **Stocker tokens de manière sécurisée**
   - localStorage (OK pour SPA)
   - httpOnly cookies (meilleur si possible)

2. **Vérifier permissions côté client**
   ```typescript
   if (user.permissions.includes('manage-students')) {
     // Afficher bouton
   }
   ```

3. **Expiration token**

4. **Pas de données sensibles en cache navigateur** (notes, dossier disciplinaire)

---

## Checklist Sécurité

- [ ] HTTPS activé en production
- [ ] Tokens Sanctum avec expiration configurée
- [ ] Rate limiting sur endpoints sensibles
- [ ] Validation stricte sur toutes entrées (notes 0-20, etc.)
- [ ] Permissions Spatie configurées par rôle (8 rôles)
- [ ] Upload fichiers avec validation type/taille
- [ ] Logs sécurité actifs (modifications notes, discipline)
- [ ] Audit trail sur données critiques (notes, paiements, statuts)
- [ ] Isolation tenant vérifiée
- [ ] Mots de passe hachés (bcrypt)
- [ ] CSRF protection activée (web)
- [ ] Headers sécurité configurés
- [ ] Pas de données sensibles dans logs
- [ ] Backups réguliers et chiffrés
- [ ] Protection données mineurs (accès restreint, consentement parental)
- [ ] Isolation Parent ↔ Enfant (un parent ne voit que ses enfants)
- [ ] Isolation Enseignant ↔ Classe (un enseignant ne voit que ses classes)
- [ ] Documents officiels avec URL signées temporaires

---

[Suivant : Prochaines Étapes →](./next-steps.md)
