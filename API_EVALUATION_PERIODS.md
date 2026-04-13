# 📋 API - Périodes d'Évaluation (Evaluation Periods)

## Endpoint de Base
```
/api/admin/semesters/{semester_id}/evaluation-periods
```

---

## 📝 Structure des Données

### Créer une Période d'Évaluation

**Endpoint:** `POST /api/admin/semesters/{semester_id}/evaluation-periods`

**Headers:**
```
Content-Type: application/json
Accept: application/json
```

**Body (JSON):**
```json
{
  "name": "Session d'examens S1",
  "type": "Session examens",
  "start_date": "2027-02-01",
  "end_date": "2027-02-28",
  "description": "Session d'examens du premier semestre 2026-2027"
}
```

---

## 📊 Champs Requis et Optionnels

| Champ | Type | Requis | Description | Exemple |
|-------|------|--------|-------------|---------|
| `name` | string | ✅ Oui | Nom de la période (max 255 caractères) | `"Session d'examens S1"` |
| `type` | string | ✅ Oui | Type de période (voir valeurs ci-dessous) | `"Session examens"` |
| `start_date` | date | ✅ Oui | Date de début (format: YYYY-MM-DD) | `"2027-02-01"` |
| `end_date` | date | ✅ Oui | Date de fin (format: YYYY-MM-DD) | `"2027-02-28"` |
| `description` | string | ❌ Non | Description optionnelle | `"Session d'examens..."` |

---

## 🎯 Valeurs Acceptées pour `type`

⚠️ **IMPORTANT:** Le champ `type` doit être **exactement** l'une des valeurs suivantes (respecter les accents et majuscules) :

| Valeur | Description | Usage |
|--------|-------------|-------|
| `"Jour férié"` | Jour férié | Jours fériés officiels |
| `"Vacances"` | Période de vacances | Vacances scolaires |
| `"Inscription pédagogique"` | Période d'inscription | Inscriptions aux cours/modules |
| `"Session examens"` | Session d'examens | Examens normaux ou rattrapages |
| `"Rattrapage"` | Session de rattrapage | Examens de rattrapage |
| `"Autre"` | Autre type | Autres types de périodes |

---

## ⚠️ Contraintes de Validation

### 1. Dates dans les limites du semestre
Les dates `start_date` et `end_date` doivent être **comprises entre les dates du semestre**.

**Exemple pour le semestre ID 3:**
- Début semestre: `29/01/2027`
- Fin semestre: `21/07/2027`

```json
{
  "start_date": "2027-02-01",  // ✅ OK (après 29/01/2027)
  "end_date": "2027-06-30"     // ✅ OK (avant 21/07/2027)
}
```

### 2. Date de fin après date de début
```json
{
  "start_date": "2027-02-01",
  "end_date": "2027-02-28"  // ✅ OK (après start_date)
}
```

### 3. Pas de chevauchement
La nouvelle période ne doit pas chevaucher une période existante du même semestre.

---

## 📌 Exemples Complets

### Exemple 1: Session d'examens
```json
{
  "name": "Session d'examens normaux - Semestre 1",
  "type": "Session examens",
  "start_date": "2027-02-01",
  "end_date": "2027-02-28",
  "description": "Session d'examens du premier semestre 2026-2027"
}
```

### Exemple 2: Rattrapage
```json
{
  "name": "Session de rattrapage",
  "type": "Rattrapage",
  "start_date": "2027-06-01",
  "end_date": "2027-06-20",
  "description": "Session de rattrapage pour les étudiants ajournés"
}
```

### Exemple 3: Vacances
```json
{
  "name": "Vacances de printemps",
  "type": "Vacances",
  "start_date": "2027-04-10",
  "end_date": "2027-04-25"
}
```

### Exemple 4: Jour férié
```json
{
  "name": "Fête du Travail",
  "type": "Jour férié",
  "start_date": "2027-05-01",
  "end_date": "2027-05-01"
}
```

### Exemple 5: Inscription pédagogique
```json
{
  "name": "Inscriptions pédagogiques S2",
  "type": "Inscription pédagogique",
  "start_date": "2027-02-01",
  "end_date": "2027-02-15",
  "description": "Période d'inscription aux modules du semestre 2"
}
```

---

## 🧪 Test avec cURL

```bash
curl -X POST http://tenant1.local/api/admin/semesters/3/evaluation-periods \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "name": "Session d'\''examens S1",
    "type": "Session examens",
    "start_date": "2027-02-01",
    "end_date": "2027-02-28",
    "description": "Session d'\''examens du premier semestre"
  }'
```

---

## ✅ Réponse Attendue (201 Created)

```json
{
  "message": "Période d'évaluation créée avec succès.",
  "data": {
    "id": 1,
    "semester_id": 3,
    "name": "Session d'examens S1",
    "type": "Session examens",
    "start_date": "2027-02-01",
    "end_date": "2027-02-28",
    "description": "Session d'examens du premier semestre",
    "created_at": "2026-01-15T13:45:01.000000Z",
    "updated_at": "2026-01-15T13:45:01.000000Z"
  }
}
```

---

## ❌ Erreurs Possibles

### 422 Unprocessable Entity - Validation échouée

```json
{
  "message": "Le type de période sélectionné est invalide. (and 1 more error)",
  "errors": {
    "type": ["Le type de période sélectionné est invalide."],
    "start_date": ["Les dates de la période doivent être dans les limites du semestre (29/01/2027 - 21/07/2027)."]
  }
}
```

### 404 Not Found - Semestre introuvable

```json
{
  "message": "No query results for model [Modules\\StructureAcademique\\Entities\\Semester] 999"
}
```

---

## 🔄 Autres Endpoints

### Lister les périodes d'un semestre
```
GET /api/admin/semesters/{semester_id}/evaluation-periods
```

### Détails d'une période
```
GET /api/admin/semesters/{semester_id}/evaluation-periods/{period_id}
```

### Modifier une période
```
PUT /api/admin/semesters/{semester_id}/evaluation-periods/{period_id}
```

### Supprimer une période
```
DELETE /api/admin/semesters/{semester_id}/evaluation-periods/{period_id}
```

### Périodes actives (global)
```
GET /api/admin/evaluation-periods/active
```

### Périodes à venir (global)
```
GET /api/admin/evaluation-periods/upcoming
```

### Calendrier annuel (global)
```
GET /api/admin/evaluation-periods/calendar?academic_year_id=1&type=Session examens
```

---

**Date:** 2026-01-15  
**Version:** 1.0  
**Statut:** ✅ Documenté et testé
