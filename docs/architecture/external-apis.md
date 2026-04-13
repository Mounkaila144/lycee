# APIs Externes

[← Retour à l'index](./index.md)

---

## Convention URL

**Format** : `/api/{niveau}/{ressource}`

Ou :
- `{niveau}` : `admin`, `frontend`, ou `superadmin`
- `{ressource}` : Nom de la ressource (pluriel, kebab-case)

---

## Authentification

Toutes les routes API necessitent :
- **Token Bearer** : `Authorization: Bearer {token}`
- **Header Tenant** : `X-Tenant-ID: {tenant_id}` (sauf routes superadmin)

**Middleware appliques** :
- Routes admin/frontend : `['tenant', 'tenant.auth']`
- Routes superadmin : `['auth:sanctum']`

---

## Format des Reponses

### Succes (200/201)
```json
{
  "message": "Operation reussie",
  "data": { ... }
}
```

### Liste avec Pagination
```json
{
  "data": [...],
  "links": {
    "first": "...",
    "last": "...",
    "prev": null,
    "next": "..."
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "to": 15,
    "per_page": 15,
    "total": 150
  }
}
```

### Erreur de Validation (422)
```json
{
  "message": "Les donnees fournies sont invalides",
  "errors": {
    "email": ["L'email est deja utilise"],
    "firstname": ["Le prenom est obligatoire"]
  }
}
```

### Erreur d'Authentification (401)
```json
{
  "message": "Non authentifie"
}
```

### Erreur de Permission (403)
```json
{
  "message": "Cette action n'est pas autorisee"
}
```

---

## Module Structure Academique (Admin)

### Annees Academiques
```
GET    /api/admin/academic-years              # Liste
POST   /api/admin/academic-years              # Creer
GET    /api/admin/academic-years/{id}         # Details
PUT    /api/admin/academic-years/{id}         # Modifier
DELETE /api/admin/academic-years/{id}         # Supprimer
POST   /api/admin/academic-years/{id}/activate  # Activer annee
```

### Cycles
```
GET    /api/admin/cycles
POST   /api/admin/cycles
GET    /api/admin/cycles/{id}
PUT    /api/admin/cycles/{id}
DELETE /api/admin/cycles/{id}
```

### Niveaux
```
GET    /api/admin/levels
POST   /api/admin/levels
GET    /api/admin/levels/{id}
PUT    /api/admin/levels/{id}
DELETE /api/admin/levels/{id}
```

### Series
```
GET    /api/admin/series
POST   /api/admin/series
GET    /api/admin/series/{id}
PUT    /api/admin/series/{id}
DELETE /api/admin/series/{id}
```

### Classes
```
GET    /api/admin/classes
POST   /api/admin/classes
GET    /api/admin/classes/{id}
PUT    /api/admin/classes/{id}
DELETE /api/admin/classes/{id}
```

### Matieres
```
GET    /api/admin/subjects
POST   /api/admin/subjects
GET    /api/admin/subjects/{id}
PUT    /api/admin/subjects/{id}
DELETE /api/admin/subjects/{id}
```

### Coefficients Matiere-Classe
```
GET    /api/admin/subject-class-coefficients
POST   /api/admin/subject-class-coefficients
GET    /api/admin/subject-class-coefficients/{id}
PUT    /api/admin/subject-class-coefficients/{id}
DELETE /api/admin/subject-class-coefficients/{id}
```

### Affectations Enseignant-Matiere
```
GET    /api/admin/teacher-subject-assignments
POST   /api/admin/teacher-subject-assignments
GET    /api/admin/teacher-subject-assignments/{id}
PUT    /api/admin/teacher-subject-assignments/{id}
DELETE /api/admin/teacher-subject-assignments/{id}
```

---

## Module Inscriptions (Admin)

### Eleves
```
GET    /api/admin/students                      # Liste paginee
POST   /api/admin/students                      # Inscrire (creation auto du parent)
GET    /api/admin/students/{id}                 # Details complets
PUT    /api/admin/students/{id}                 # Modifier
DELETE /api/admin/students/{id}                 # Supprimer (soft delete)
PUT    /api/admin/students/{id}/status          # Changer statut
POST   /api/admin/students/import               # Import CSV
POST   /api/admin/students/import/preview       # Previsualiser import
```

### Parents
```
GET    /api/admin/parents
POST   /api/admin/parents
GET    /api/admin/parents/{id}
PUT    /api/admin/parents/{id}
DELETE /api/admin/parents/{id}
```

### Affectations en Classe
```
GET    /api/admin/class-enrollments              # Liste des affectations
POST   /api/admin/class-enrollments              # Affecter un eleve a une classe
```

---

## Module Notes & Evaluations

### Evaluations (Admin)
```
GET    /api/admin/evaluations
POST   /api/admin/evaluations
GET    /api/admin/evaluations/{id}
PUT    /api/admin/evaluations/{id}
DELETE /api/admin/evaluations/{id}
POST   /api/admin/evaluations/{id}/publish       # Publier resultats
GET    /api/admin/classes/{id}/grade-summary      # Recapitulatif de classe
```

### Notes (Enseignant - Frontend)
```
GET    /api/frontend/my-classes                  # Classes assignees
GET    /api/frontend/my-subjects                 # Matieres assignees
GET    /api/frontend/evaluations/{id}/students   # Liste eleves pour saisie
POST   /api/frontend/grades                      # Saisir notes (bulk)
PUT    /api/frontend/grades/{id}                 # Modifier note
POST   /api/frontend/appreciations               # Saisie appreciations par matiere
```

### Notes (Eleve - Frontend)
```
GET    /api/frontend/my-grades                   # Mes notes
GET    /api/frontend/my-report-cards             # Mes bulletins
GET    /api/frontend/my-averages                 # Mes moyennes
```

---

## Module Conseil de Classe (Admin)

### Conseils de Classe
```
GET    /api/admin/class-councils
POST   /api/admin/class-councils
GET    /api/admin/class-councils/{id}
PUT    /api/admin/class-councils/{id}
DELETE /api/admin/class-councils/{id}
GET    /api/admin/class-councils/{id}/summary    # Resume du conseil
POST   /api/admin/class-councils/{id}/decisions  # Decisions en masse (bulk)
POST   /api/admin/class-councils/{id}/finalize   # Finaliser le conseil
GET    /api/admin/class-councils/{id}/minutes    # Telecharger PV (PDF)
```

---

## Module Presences/Absences

### Presences (Enseignant - Frontend)
```
GET    /api/frontend/timetable-slots/{id}/students  # Liste eleves pour appel
POST   /api/frontend/attendances                    # Enregistrer appel (bulk)
PUT    /api/frontend/attendances/{id}               # Modifier presence
```

### Presences (Admin)
```
GET    /api/admin/attendances                       # Toutes les presences (filtrable par classe, eleve, periode)
GET    /api/admin/students/{id}/attendances         # Presences d'un eleve
GET    /api/admin/attendance-stats                  # Statistiques de presences
```

---

## Module Discipline (Admin)

### Incidents Disciplinaires
```
GET    /api/admin/disciplinary-incidents
POST   /api/admin/disciplinary-incidents
GET    /api/admin/disciplinary-incidents/{id}
PUT    /api/admin/disciplinary-incidents/{id}
DELETE /api/admin/disciplinary-incidents/{id}
```

### Sanctions Disciplinaires
```
GET    /api/admin/disciplinary-sanctions
POST   /api/admin/disciplinary-sanctions
GET    /api/admin/disciplinary-sanctions/{id}
PUT    /api/admin/disciplinary-sanctions/{id}
DELETE /api/admin/disciplinary-sanctions/{id}
```

### Dossier Disciplinaire d'un Eleve
```
GET    /api/admin/students/{id}/discipline          # Dossier disciplinaire complet
```

### Conseils de Discipline
```
GET    /api/admin/disciplinary-councils
POST   /api/admin/disciplinary-councils
GET    /api/admin/disciplinary-councils/{id}
PUT    /api/admin/disciplinary-councils/{id}
DELETE /api/admin/disciplinary-councils/{id}
POST   /api/admin/disciplinary-councils/{id}/decision  # Enregistrer la decision
```

---

## Module Emplois du Temps

### Salles (Admin)
```
GET    /api/admin/rooms
POST   /api/admin/rooms
GET    /api/admin/rooms/{id}
PUT    /api/admin/rooms/{id}
DELETE /api/admin/rooms/{id}
```

### Creneaux (Admin)
```
GET    /api/admin/timetable-slots
POST   /api/admin/timetable-slots
GET    /api/admin/timetable-slots/{id}
PUT    /api/admin/timetable-slots/{id}
DELETE /api/admin/timetable-slots/{id}
POST   /api/admin/timetable-slots/check-conflicts   # Verifier conflits
```

### Emplois du Temps (Enseignant/Eleve - Frontend)
```
GET    /api/frontend/my-timetable                   # Mon emploi du temps
```

---

## Module Portail Parent (Frontend)

### Enfants & Suivi
```
GET    /api/frontend/my-children                    # Liste de mes enfants
GET    /api/frontend/children/{id}/grades           # Notes d'un enfant
GET    /api/frontend/children/{id}/absences         # Absences d'un enfant
GET    /api/frontend/children/{id}/discipline       # Dossier disciplinaire d'un enfant
GET    /api/frontend/children/{id}/report-cards     # Bulletins d'un enfant
GET    /api/frontend/children/{id}/fees             # Frais scolaires d'un enfant
```

---

## Module Comptabilite (Admin)

### Types de Frais
```
GET    /api/admin/fee-types
POST   /api/admin/fee-types
GET    /api/admin/fee-types/{id}
PUT    /api/admin/fee-types/{id}
DELETE /api/admin/fee-types/{id}
```

### Frais Eleves
```
GET    /api/admin/student-fees
POST   /api/admin/student-fees                      # Attribuer frais
GET    /api/admin/students/{id}/fees                # Frais d'un eleve
```

### Paiements
```
GET    /api/admin/student-payments
POST   /api/admin/student-payments                  # Enregistrer paiement
GET    /api/admin/student-payments/{id}/receipt     # Telecharger recu
```

### Tableau de Bord Financier
```
GET    /api/admin/financial-dashboard               # Dashboard financier
```

### Depenses
```
GET    /api/admin/expenses
POST   /api/admin/expenses
GET    /api/admin/expenses/{id}
PUT    /api/admin/expenses/{id}
DELETE /api/admin/expenses/{id}
```

### Echeanciers de Paiement
```
GET    /api/admin/payment-schedules
POST   /api/admin/payment-schedules
GET    /api/admin/payment-schedules/{id}
PUT    /api/admin/payment-schedules/{id}
DELETE /api/admin/payment-schedules/{id}
```

---

## Module Paie Personnel

### Contrats Personnel (Admin)
```
GET    /api/admin/staff-contracts
POST   /api/admin/staff-contracts
GET    /api/admin/staff-contracts/{id}
PUT    /api/admin/staff-contracts/{id}
DELETE /api/admin/staff-contracts/{id}
```

### Fiches de Paie (Admin)
```
GET    /api/admin/payroll-records
POST   /api/admin/payroll-records                   # Creer fiche de paie
GET    /api/admin/payroll-records/{id}
PUT    /api/admin/payroll-records/{id}
DELETE /api/admin/payroll-records/{id}
POST   /api/admin/payroll-records/{id}/approve      # Approuver
GET    /api/admin/payroll-records/{id}/bulletin     # Telecharger bulletin
```

### Fiches de Paie (Personnel - Frontend)
```
GET    /api/frontend/my-payroll-records             # Mes bulletins de paie
GET    /api/frontend/payroll-records/{id}/bulletin  # Telecharger bulletin
```

---

## Module Documents Officiels

### Documents (Admin)
```
POST   /api/admin/documents/bulletin-semestriel         # Generer bulletin semestriel
POST   /api/admin/documents/bulletin-semestriel/batch   # Generation par lot (classe entiere)
POST   /api/admin/documents/attestation-scolarite       # Generer attestation de scolarite
POST   /api/admin/documents/carte-scolaire              # Generer carte scolaire
GET    /api/admin/generated-documents                   # Historique des documents generes
GET    /api/admin/generated-documents/{id}/download     # Telecharger document
```

### Documents (Eleve/Parent - Frontend)
```
GET    /api/frontend/my-documents                       # Mes documents
GET    /api/frontend/documents/{id}/download            # Telecharger document
```

---

[Suivant : Workflows Metier →](./core-workflows.md)
