# Guide Frontend - Module Inscriptions (Enrollment)

**Destinataire:** Équipe Frontend (Dev Agent)
**Date:** 2026-01-18
**Objet:** Architecture du modèle Student et Enrollments - Éviter les erreurs de conception

---

## 🎯 Résumé Exécutif

Le module Inscriptions utilise une **architecture normalisée** avec séparation entre:
- **Student** (informations personnelles de l'étudiant)
- **StudentEnrollment** (inscriptions aux programmes)

**❌ ERREUR COMMUNE:** Demander d'ajouter `programme_id` directement sur la table `students`
**✅ CORRECT:** Utiliser la relation `enrollments` pour obtenir les programmes

---

## 📊 Modèle de Données - Architecture Complète

### 1. Table `students` - Informations Personnelles

```sql
CREATE TABLE students (
    id BIGINT PRIMARY KEY,
    matricule VARCHAR(50) UNIQUE,           -- Format: 2026-INF-001

    -- Identité
    firstname VARCHAR(255),
    lastname VARCHAR(255),
    birthdate DATE,
    birthplace VARCHAR(255),
    sex ENUM('M', 'F', 'O'),
    nationality VARCHAR(255),

    -- Contact
    email VARCHAR(255) UNIQUE,
    phone VARCHAR(20),
    mobile VARCHAR(20),
    address TEXT,
    city VARCHAR(255),
    country VARCHAR(255),

    -- Autres
    photo VARCHAR(255),
    status ENUM('Actif', 'Suspendu', 'Exclu', 'Diplômé'),
    emergency_contact_name VARCHAR(255),
    emergency_contact_phone VARCHAR(20),

    -- ❌ PAS DE programme_id ICI !

    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP
);
```

**Points clés:**
- Un `Student` = une personne physique avec ses informations personnelles
- Le matricule est généré **UNE SEULE FOIS** lors de la création (format: `{YEAR}-{PROGRAMME_CODE}-{SEQUENCE}`)
- **Aucun lien direct avec un programme** dans cette table

---

### 2. Table `student_enrollments` - Inscriptions aux Programmes

```sql
CREATE TABLE student_enrollments (
    id BIGINT PRIMARY KEY,
    student_id BIGINT,                      -- ← Étudiant
    programme_id BIGINT,                    -- ← Programme (ICI!)
    academic_year_id BIGINT,                -- ← Année académique (ex: 2025-2026)
    semester_id BIGINT,                     -- ← Semestre (S1, S2)
    level VARCHAR(10),                      -- L1, L2, L3, M1, M2
    group_id BIGINT NULLABLE,               -- Groupe TD/TP optionnel

    enrollment_date DATE,
    status ENUM('Actif', 'Suspendu', 'Annulé', 'Terminé'),
    notes TEXT,
    enrolled_by BIGINT,

    created_at TIMESTAMP,
    updated_at TIMESTAMP,
    deleted_at TIMESTAMP,

    UNIQUE KEY (student_id, programme_id, academic_year_id, semester_id)
);
```

**Pourquoi cette séparation ?**
1. ✅ Un étudiant peut changer de programme (transfert, réorientation)
2. ✅ Un étudiant peut avoir plusieurs inscriptions historiques
3. ✅ Un étudiant peut être inscrit à plusieurs programmes en parallèle (rare, mais possible)
4. ✅ Historique complet des parcours académiques

---

## 🔄 Workflow de Création d'un Étudiant

### Étape 1: Création du Dossier Étudiant (Story actuelle)
```http
POST /api/admin/enrollment/students
Content-Type: application/json

{
  "firstname": "Mounkaila",
  "lastname": "Boubacar",
  "birthdate": "2005-03-15",
  "sex": "M",
  "email": "mounkaila@example.com",
  "mobile": "+227XXXXXXXX",
  "nationality": "Niger",
  "country": "Niger",
  "programme_id": 1        // ← Utilisé UNIQUEMENT pour générer le matricule!
}
```

**Réponse:**
```json
{
  "message": "Dossier étudiant créé avec succès",
  "data": {
    "id": 1,
    "matricule": "2026-INF-001",    // ← Généré avec le code du programme
    "firstname": "Mounkaila",
    "lastname": "Boubacar",
    // ... autres champs
    // ❌ PAS de "programme": {...} ici !
  }
}
```

**Important:** Le `programme_id` dans le request sert à:
- Générer le matricule au format `{YEAR}-{CODE_PROGRAMME}-{SEQUENCE}`
- Il n'est **PAS stocké** dans la table `students`

---

### Étape 2: Inscription Pédagogique (Story séparée)
```http
POST /api/admin/enrollment/students/{studentId}/enrollments
Content-Type: application/json

{
  "programme_id": 1,
  "academic_year_id": 5,
  "semester_id": 1,
  "level": "L1",
  "enrollment_date": "2026-01-15"
}
```

**Réponse:**
```json
{
  "message": "Inscription pédagogique créée avec succès",
  "data": {
    "id": 10,
    "student_id": 1,
    "programme_id": 1,
    "academic_year_id": 5,
    "semester_id": 1,
    "level": "L1",
    "status": "Actif",
    "programme": {
      "id": 1,
      "name": "Licence Informatique",
      "code": "INF"
    }
  }
}
```

---

## 📡 Récupération des Données - Bonnes Pratiques

### ❌ INCORRECT - Ce qu'il NE FAUT PAS demander

```http
GET /api/admin/enrollment/students?with=programme
```
**Pourquoi c'est incorrect ?**
- Il n'y a pas de relation directe `Student → Programme`
- Cela ne correspond pas au modèle de données

---

### ✅ CORRECT - Requêtes Valides

#### Option 1: Liste des étudiants (sans programmes)
```http
GET /api/admin/enrollment/students?per_page=10&page=1
```
**Réponse:**
```json
{
  "data": [
    {
      "id": 1,
      "matricule": "2026-INF-001",
      "firstname": "Mounkaila",
      "lastname": "Boubacar",
      "email": "mounkaila@example.com",
      "status": "Actif"
      // ... pas de programme ici
    }
  ],
  "meta": { ... }
}
```

**Utilisation:** Table de listing simple, recherche rapide

---

#### Option 2: Détails d'un étudiant avec ses inscriptions
```http
GET /api/admin/enrollment/students/{id}/enrollments
```
**Réponse:**
```json
{
  "data": [
    {
      "id": 10,
      "student_id": 1,
      "programme": {
        "id": 1,
        "name": "Licence Informatique",
        "code": "INF"
      },
      "academic_year": {
        "id": 5,
        "name": "2025-2026"
      },
      "semester": {
        "id": 1,
        "name": "Semestre 1"
      },
      "level": "L1",
      "status": "Actif",
      "enrollment_date": "2026-01-15"
    }
  ]
}
```

**Utilisation:** Afficher l'historique complet des inscriptions d'un étudiant

---

#### Option 3: Détails étudiant + inscription active
```http
GET /api/admin/enrollment/students/{id}?include=active_enrollment
```
**Réponse:**
```json
{
  "data": {
    "id": 1,
    "matricule": "2026-INF-001",
    "firstname": "Mounkaila",
    "lastname": "Boubacar",
    // ... infos personnelles

    "active_enrollment": {
      "id": 10,
      "programme": {
        "id": 1,
        "name": "Licence Informatique",
        "code": "INF"
      },
      "level": "L1",
      "status": "Actif"
    }
  }
}
```

**Utilisation:** Fiche étudiant avec son inscription en cours

---

## 🎨 Recommandations UI/UX Frontend

### 1. Page Liste des Étudiants
```typescript
// ✅ CORRECT
const StudentListTable = () => {
  const { data: students } = useStudents();

  return (
    <DataGrid
      columns={[
        { field: 'matricule', header: 'Matricule' },
        { field: 'full_name', header: 'Nom Complet' },
        { field: 'email', header: 'Email' },
        { field: 'status', header: 'Statut' },
        // ❌ Pas de colonne "Programme" ici, ce n'est pas dans le modèle!
      ]}
      data={students}
    />
  );
};
```

**Si vous voulez afficher le programme actif:**
```typescript
// Option A: Charger séparément pour chaque étudiant (N+1, pas optimal)
// ❌ NE PAS FAIRE

// Option B: Utiliser un endpoint dédié
// ✅ DEMANDER AU BACKEND:
// GET /api/admin/enrollment/students-with-enrollments
// Qui retourne students avec leur enrollment actif en 1 seule requête
```

---

### 2. Page Détails Étudiant
```typescript
const StudentDetailPage = ({ studentId }: Props) => {
  const { data: student } = useStudent(studentId);
  const { data: enrollments } = useStudentEnrollments(studentId);

  return (
    <>
      {/* Informations personnelles */}
      <StudentInfoCard student={student} />

      {/* Inscriptions académiques */}
      <EnrollmentHistoryTable enrollments={enrollments} />
    </>
  );
};
```

---

### 3. Formulaire Création Étudiant
```typescript
const CreateStudentForm = () => {
  return (
    <Form onSubmit={handleSubmit}>
      {/* Étape 1: Informations personnelles */}
      <TextField name="firstname" label="Prénom" required />
      <TextField name="lastname" label="Nom" required />
      <DatePicker name="birthdate" label="Date de naissance" required />
      {/* ... autres champs personnels */}

      {/* Programme pour génération matricule UNIQUEMENT */}
      <ProgrammeSelect
        name="programme_id"
        label="Programme (pour matricule)"
        required
        helperText="Utilisé pour générer le matricule (ex: 2026-INF-001)"
      />

      <Button type="submit">Créer le Dossier</Button>

      {/* ℹ️ Message après création */}
      <Alert severity="info">
        Après création du dossier, vous devrez effectuer l'inscription
        pédagogique pour associer l'étudiant à un programme/année/semestre.
      </Alert>
    </Form>
  );
};
```

---

## 🚨 Erreurs Fréquentes à Éviter

### Erreur #1: Demander `programme_id` sur Student
```typescript
// ❌ INCORRECT
interface Student {
  id: number;
  matricule: string;
  firstname: string;
  programme_id: number;  // ← NE DEVRAIT PAS EXISTER!
}
```

**Solution:** Utiliser StudentEnrollment
```typescript
// ✅ CORRECT
interface Student {
  id: number;
  matricule: string;
  firstname: string;
  // ... pas de programme_id
}

interface StudentEnrollment {
  id: number;
  student_id: number;
  programme_id: number;  // ← ICI OUI!
  level: string;
  status: string;
}
```

---

### Erreur #2: Afficher un programme unique pour l'étudiant
```typescript
// ❌ INCORRECT - Suppose qu'un étudiant = 1 programme
<Typography>Programme: {student.programme?.name}</Typography>

// ✅ CORRECT - Afficher l'inscription active ou l'historique
<Typography>
  Inscription actuelle: {activeEnrollment?.programme?.name} (L{activeEnrollment?.level})
</Typography>

<EnrollmentHistory enrollments={allEnrollments} />
```

---

### Erreur #3: Demander au backend d'ajouter `programme_id`
**Mauvaise demande:**
> "Peux-tu ajouter `programme_id` dans la table `students` pour simplifier le frontend ?"

**Pourquoi c'est incorrect:**
- Casse l'architecture normalisée
- Perd l'historique des changements de programme
- Ne permet pas les transferts/réorientations
- Crée des incohérences de données

**Bonne demande:**
> "Peux-tu créer un endpoint qui retourne les étudiants avec leur inscription active en une seule requête ?"

---

## 📚 Endpoints à Utiliser

### Déjà Implémentés
| Méthode | Route | Description |
|---------|-------|-------------|
| GET | `/api/admin/enrollment/students` | Liste étudiants (sans programmes) |
| POST | `/api/admin/enrollment/students` | Créer dossier étudiant |
| GET | `/api/admin/enrollment/students/{id}` | Détails étudiant |
| PUT | `/api/admin/enrollment/students/{id}` | Modifier étudiant |
| POST | `/api/admin/enrollment/students/{id}/documents` | Upload documents |

### À Demander au Backend (si besoin)
| Endpoint Suggéré | Description |
|------------------|-------------|
| `GET /api/admin/enrollment/students/{id}/enrollments` | Inscriptions d'un étudiant |
| `GET /api/admin/enrollment/students-with-active-enrollment` | Liste avec inscription active (optimisé) |
| `POST /api/admin/enrollment/students/{id}/enrollments` | Créer inscription pédagogique |

---

## ✅ Checklist Frontend

Avant de demander une modification au backend:
- [ ] J'ai vérifié le modèle de données dans la story
- [ ] Je comprends la différence entre Student et StudentEnrollment
- [ ] Ma demande respecte l'architecture normalisée
- [ ] Je n'ajoute pas de champs qui créent de la redondance
- [ ] J'ai vérifié les endpoints existants avant de demander un nouveau

---

## 📞 Questions Fréquentes

**Q: Pourquoi le matricule contient le code programme mais pas de lien direct ?**
R: Le matricule est généré **une seule fois** lors de la création. C'est un identifiant unique qui capture le programme d'origine, mais l'étudiant peut ensuite changer de programme via les enrollments.

**Q: Comment afficher le programme actuel d'un étudiant ?**
R: Charger ses `enrollments` et filtrer celui avec `status = 'Actif'` pour l'année en cours.

**Q: Un étudiant peut avoir plusieurs programmes ?**
R: Oui, via plusieurs `StudentEnrollment` (historique ou parallèle, selon les règles métier).

**Q: Dois-je afficher le programme dans la liste des étudiants ?**
R: Pas forcément. Si oui, demander un endpoint optimisé au backend (ex: `students-with-active-enrollment`) pour éviter N+1 queries.

---

**Contact Backend:** Demander clarifications sur #enrollment-module avant de modifier le modèle de données.