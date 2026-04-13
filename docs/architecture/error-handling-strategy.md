# Stratégie de Gestion des Erreurs

[← Retour à l'index](./index.md)

---

## Gestion des Erreurs Backend (Laravel)

### 1. Erreurs de Validation (422)

**Form Requests** :
```php
namespace Modules\Inscriptions\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'birthdate' => ['required', 'date', 'before:today'],
            'sex' => ['required', 'in:M,F'],
            'nationality' => ['required', 'string', 'max:100'],
            'parent_phone' => ['required', 'regex:/^[+]?[0-9\s\-\(\)]+$/'],
            'class_id' => ['required', 'exists:classes,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'birthdate.before' => 'La date de naissance doit être antérieure à aujourd\'hui.',
            'class_id.exists' => 'La classe sélectionnée n\'existe pas.',
            'parent_phone.regex' => 'Le numéro de téléphone du parent est invalide.',
        ];
    }
}
```

**Réponse automatique** :
```json
{
  "message": "Les données fournies sont invalides",
  "errors": {
    "class_id": ["La classe sélectionnée n'existe pas."],
    "birthdate": ["La date de naissance doit être antérieure à aujourd'hui."]
  }
}
```

### 2. Erreurs d'Authentification (401)

**Non authentifié** :
```json
{
  "message": "Non authentifié"
}
```

**Token expiré** :
```json
{
  "message": "Token expiré"
}
```

### 3. Erreurs d'Autorisation (403)

**Permissions insuffisantes** :
```php
// Dans Controller
public function destroy(Student $student)
{
    $this->authorize('delete', $student);

    $student->delete();
}
```

**Réponse** :
```json
{
  "message": "Cette action n'est pas autorisée"
}
```

### 4. Erreurs Métier (400)

**Logique métier** :
```php
public function storeGrade(StoreGradeRequest $request)
{
    // Vérification : enseignant assigné à cette classe/matière
    $assignment = TeacherSubjectAssignment::where('teacher_id', auth()->id())
        ->where('class_id', $request->class_id)
        ->where('subject_id', $request->subject_id)
        ->first();

    if (!$assignment) {
        return response()->json([
            'message' => 'Vous n\'êtes pas assigné à cette matière pour cette classe',
            'error_code' => 'GRADES_TEACHER_NOT_ASSIGNED'
        ], 400);
    }

    // Vérification : note entre 0 et 20
    if ($request->score < 0 || $request->score > 20) {
        return response()->json([
            'message' => 'La note doit être comprise entre 0 et 20',
            'error_code' => 'GRADES_SCORE_OUT_OF_RANGE'
        ], 400);
    }

    // Logique saisie note
}
```

### 5. Erreurs Serveur (500)

**Exception Handler** (`bootstrap/app.php`) :
```php
->withExceptions(function (Exceptions $exceptions) {
    $exceptions->render(function (ModelNotFoundException $e, Request $request) {
        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Ressource introuvable'
            ], 404);
        }
    });

    $exceptions->render(function (QueryException $e, Request $request) {
        if ($request->expectsJson()) {
            Log::error('Database error', [
                'tenant_id' => tenant('id'),
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur serveur, veuillez réessayer'
            ], 500);
        }
    });
})
```

### 6. Logging

**Niveaux de logs** :
- **Emergency** : Système inutilisable
- **Alert** : Action immédiate requise
- **Critical** : Conditions critiques
- **Error** : Erreurs runtime
- **Warning** : Avertissements
- **Notice** : Événements normaux mais significatifs
- **Info** : Informations
- **Debug** : Informations de debug

**Exemple** :
```php
// Erreur critique - génération bulletin échouée
Log::critical('Failed to generate report card', [
    'tenant_id' => tenant('id'),
    'class_id' => $class->id,
    'semester_id' => $semester->id,
    'error' => $e->getMessage(),
]);

// Information
Log::info('Student enrolled successfully', [
    'tenant_id' => tenant('id'),
    'student_id' => $student->id,
    'class_id' => $class->id,
]);
```

---

## Gestion des Erreurs Frontend (Next.js)

### 1. Erreurs API (Axios Interceptor)

**Configuration** :
```typescript
// lib/api/apiClient.ts
import axios from 'axios';

const apiClient = axios.create({
  baseURL: process.env.NEXT_PUBLIC_API_URL,
});

// Response interceptor
apiClient.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response) {
      const { status, data } = error.response;

      switch (status) {
        case 401:
          // Rediriger vers login
          window.location.href = '/login';
          break;

        case 403:
          // Afficher message permission
          alert('Vous n\'avez pas la permission pour cette action');
          break;

        case 422:
          // Erreurs de validation (gérées par formulaire)
          break;

        case 500:
          // Erreur serveur
          alert('Erreur serveur, veuillez réessayer');
          break;

        default:
          alert(data.message || 'Une erreur est survenue');
      }
    } else if (error.request) {
      // Pas de réponse serveur (connexion limitée - contexte Niger)
      alert('Impossible de contacter le serveur. Vérifiez votre connexion.');
    }

    return Promise.reject(error);
  }
);

export default apiClient;
```

### 2. Gestion Erreurs dans Composants

**Avec React Hook Form** :
```typescript
import { useForm } from 'react-hook-form';

const StudentForm = () => {
  const { register, handleSubmit, setError, formState: { errors } } = useForm();

  const onSubmit = async (data) => {
    try {
      await studentService.create(data);
      alert('Élève inscrit avec succès');
    } catch (error) {
      // Erreurs validation backend
      if (error.response?.status === 422) {
        const backendErrors = error.response.data.errors;

        Object.keys(backendErrors).forEach((field) => {
          setError(field, {
            type: 'manual',
            message: backendErrors[field][0],
          });
        });
      }
    }
  };

  return (
    <form onSubmit={handleSubmit(onSubmit)}>
      <input {...register('email')} />
      {errors.email && <span>{errors.email.message}</span>}
    </form>
  );
};
```

### 3. Error Boundaries

**Composant Error Boundary** :
```typescript
// components/ErrorBoundary.tsx
import React from 'react';

class ErrorBoundary extends React.Component {
  state = { hasError: false, error: null };

  static getDerivedStateFromError(error) {
    return { hasError: true, error };
  }

  componentDidCatch(error, errorInfo) {
    console.error('Error caught by boundary:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div>
          <h2>Une erreur est survenue</h2>
          <p>{this.state.error?.message}</p>
          <button onClick={() => window.location.reload()}>
            Recharger la page
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}

export default ErrorBoundary;
```

### 4. Toast Notifications

**Avec Material-UI Snackbar** :
```typescript
import { Snackbar, Alert } from '@mui/material';

const useNotification = () => {
  const [open, setOpen] = useState(false);
  const [message, setMessage] = useState('');
  const [severity, setSeverity] = useState<'success' | 'error' | 'warning'>('success');

  const showNotification = (msg: string, type: 'success' | 'error' | 'warning' = 'success') => {
    setMessage(msg);
    setSeverity(type);
    setOpen(true);
  };

  const NotificationComponent = (
    <Snackbar open={open} autoHideDuration={6000} onClose={() => setOpen(false)}>
      <Alert severity={severity} onClose={() => setOpen(false)}>
        {message}
      </Alert>
    </Snackbar>
  );

  return { showNotification, NotificationComponent };
};
```

---

## Codes d'Erreur Métier

### Convention

**Format** : `{MODULE}_{ENTITY}_{ERROR_TYPE}`

### Exemples

| Code | Description |
|------|-------------|
| `STRUCTURE_YEAR_NOT_ACTIVE` | Année scolaire non active |
| `STRUCTURE_CLASS_FULL` | Effectif maximum de la classe atteint |
| `INSCRIPTIONS_STUDENT_ALREADY_ENROLLED` | Élève déjà inscrit dans une classe cette année |
| `INSCRIPTIONS_STUDENT_NOT_ACTIVE` | Élève non actif |
| `GRADES_TEACHER_NOT_ASSIGNED` | Enseignant non assigné à cette matière/classe |
| `GRADES_SCORE_OUT_OF_RANGE` | Note hors plage (0-20) |
| `GRADES_EVALUATION_LOCKED` | Évaluation verrouillée (notes finalisées) |
| `CONSEIL_SESSION_NOT_OPEN` | Session de conseil de classe non ouverte |
| `CONSEIL_GRADES_INCOMPLETE` | Notes incomplètes pour le conseil |
| `DOCUMENTS_GENERATION_FAILED` | Échec génération PDF |
| `ATTENDANCE_SESSION_EXPIRED` | Session d'appel expirée |
| `DISCIPLINE_SANCTION_INVALID` | Type de sanction invalide |
| `TIMETABLE_TEACHER_CONFLICT` | Enseignant déjà occupé sur ce créneau |
| `TIMETABLE_ROOM_CONFLICT` | Salle déjà occupée sur ce créneau |
| `FINANCE_PAYMENT_EXCEEDS_BALANCE` | Paiement supérieur au solde restant |
| `FINANCE_FEE_NOT_CONFIGURED` | Frais non configurés pour cette classe |
| `PAYROLL_CONTRACT_EXPIRED` | Contrat personnel expiré |

---

## Stratégie de Retry

### Backend (Jobs)

```php
class GenerateReportCardJob implements ShouldQueue
{
    public $tries = 3;        // Nombre de tentatives
    public $backoff = [60, 120, 300];  // Délais entre tentatives (secondes)

    public function handle()
    {
        // Logique génération bulletin
    }

    public function failed(Throwable $exception)
    {
        // Notifier admin de l'échec
        Log::error('Report card generation failed after retries', [
            'class_id' => $this->class->id,
            'semester_id' => $this->semester->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

### Frontend (Axios Retry)

```typescript
import axiosRetry from 'axios-retry';

axiosRetry(apiClient, {
  retries: 3,
  retryDelay: axiosRetry.exponentialDelay,
  retryCondition: (error) => {
    // Retry sur erreurs réseau ou 5xx
    return axiosRetry.isNetworkOrIdempotentRequestError(error)
      || (error.response?.status >= 500);
  },
});
```

---

[Suivant : Standards de Codage →](./coding-standards.md)
