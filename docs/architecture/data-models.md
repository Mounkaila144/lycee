# Modèles de Données

[← Retour à l'index](./index.md)

---

## Vue d'Ensemble

L'architecture de données comprend **45+ nouvelles tables** réparties sur 12 modules, toutes créées dans les bases **tenant** (aucune modification de la base centrale).

### Résumé par Module

| Module | Nombre de Tables | Type |
|--------|------------------|------|
| Structure Académique | 9 | Tenant |
| Inscriptions | 5 | Tenant |
| Notes & Évaluations | 5 | Tenant |
| Conseil de Classe | 3 | Tenant |
| Documents Officiels | 1 | Tenant |
| Présences & Absences | 2 | Tenant |
| Discipline | 4 | Tenant |
| Emplois du Temps | 2 | Tenant |
| Comptabilité & Finances | 5 | Tenant |
| Paie Personnel | 2 | Tenant |
| **TOTAL** | **38 tables** | - |

> Le module Portail Parent et Statistiques & Reporting n'ont pas de tables propres — ils agrègent les données des autres modules.

---

## Module 1 : Structure Académique

### 1.1 `academic_years` (Années Scolaires)
```sql
CREATE TABLE academic_years (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL COMMENT 'ex: 2025-2026',
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE COMMENT 'Une seule active à la fois',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 1.2 `semesters` (Semestres)
```sql
CREATE TABLE semesters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    name ENUM('S1','S2') NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    is_active BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    UNIQUE KEY unique_semester (academic_year_id, name)
);
```

### 1.3 `cycles` (Cycles d'Enseignement)
```sql
CREATE TABLE cycles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL COMMENT 'ex: COLLEGE, LYCEE',
    name VARCHAR(100) NOT NULL COMMENT 'ex: Collège, Lycée',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 1.4 `levels` (Niveaux de Classe)
```sql
CREATE TABLE levels (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    cycle_id BIGINT UNSIGNED NOT NULL,
    code VARCHAR(20) UNIQUE NOT NULL COMMENT 'ex: 6E, 5E, 4E, 3E, 2NDE, 1ERE, TLE',
    name VARCHAR(100) NOT NULL COMMENT 'ex: Sixième, Cinquième, Terminale',
    order_index TINYINT UNSIGNED NOT NULL COMMENT 'Ordre chronologique (1=6e, 7=Tle)',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (cycle_id) REFERENCES cycles(id) ON DELETE CASCADE
);
```

### 1.5 `series` (Séries - Lycée uniquement)
```sql
CREATE TABLE series (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL COMMENT 'ex: A, C, D',
    name VARCHAR(100) NOT NULL COMMENT 'ex: Littéraire, Maths-Physique, Sciences Naturelles',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

### 1.6 `classes` (Classes)
```sql
CREATE TABLE classes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    level_id BIGINT UNSIGNED NOT NULL,
    series_id BIGINT UNSIGNED NULL COMMENT 'NULL pour collège, obligatoire pour lycée',
    name VARCHAR(50) NOT NULL COMMENT 'ex: 6e A, Tle C1',
    max_capacity SMALLINT UNSIGNED DEFAULT 60,
    head_teacher_id BIGINT UNSIGNED NULL COMMENT 'Professeur principal - FK -> users.id',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE RESTRICT,
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE SET NULL,
    FOREIGN KEY (head_teacher_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_class (academic_year_id, name)
);
```

### 1.7 `subjects` (Matières)
```sql
CREATE TABLE subjects (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ex: MATH, FRAN, SVT, PHY',
    name VARCHAR(255) NOT NULL COMMENT 'ex: Mathématiques, Français',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

### 1.8 `subject_class_coefficients` (Coefficients par Matière/Classe)
```sql
CREATE TABLE subject_class_coefficients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id BIGINT UNSIGNED NOT NULL,
    level_id BIGINT UNSIGNED NOT NULL,
    series_id BIGINT UNSIGNED NULL COMMENT 'NULL = toutes séries de ce niveau',
    coefficient DECIMAL(3,1) NOT NULL COMMENT 'ex: 5.0 pour Maths en Tle C',
    hours_per_week TINYINT UNSIGNED NULL COMMENT 'Heures hebdomadaires',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (level_id) REFERENCES levels(id) ON DELETE CASCADE,
    FOREIGN KEY (series_id) REFERENCES series(id) ON DELETE SET NULL,
    UNIQUE KEY unique_coefficient (subject_id, level_id, series_id)
);
```

### 1.9 `teacher_subject_assignments` (Affectations Enseignant ↔ Matière ↔ Classe)
```sql
CREATE TABLE teacher_subject_assignments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    teacher_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    subject_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    UNIQUE KEY unique_assignment (teacher_id, subject_id, class_id, academic_year_id),
    INDEX idx_teacher (teacher_id, academic_year_id),
    INDEX idx_class (class_id, academic_year_id)
);
```

---

## Module 2 : Inscriptions

### 2.1 `students` (Élèves)
```sql
CREATE TABLE students (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    matricule VARCHAR(50) UNIQUE NOT NULL COMMENT 'Auto-généré',
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    birthdate DATE NOT NULL,
    birthplace VARCHAR(255) NULL,
    sex ENUM('M','F') NOT NULL,
    nationality VARCHAR(100) DEFAULT 'Nigérienne',
    address TEXT NULL,
    phone VARCHAR(20) NULL COMMENT 'Pour élèves lycée',
    photo VARCHAR(255) NULL COMMENT 'Chemin fichier photo',
    emergency_contact_name VARCHAR(255) NULL,
    emergency_contact_phone VARCHAR(20) NULL,
    status ENUM('Actif','Transféré','Exclu','Diplômé','Redoublant') DEFAULT 'Actif',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    INDEX idx_matricule (matricule),
    INDEX idx_status (status),
    INDEX idx_name (lastname, firstname)
);
```

### 2.2 `parents` (Parents / Tuteurs)
```sql
CREATE TABLE parents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NULL COMMENT 'FK -> users.id (compte connecté)',
    firstname VARCHAR(255) NOT NULL,
    lastname VARCHAR(255) NOT NULL,
    relationship ENUM('Père','Mère','Tuteur','Tutrice','Autre') NOT NULL,
    phone VARCHAR(20) NOT NULL,
    phone_secondary VARCHAR(20) NULL,
    email VARCHAR(255) NULL,
    profession VARCHAR(255) NULL,
    address TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_phone (phone),
    INDEX idx_user (user_id)
);
```

### 2.3 `student_parent` (Pivot Élève ↔ Parent)
```sql
CREATE TABLE student_parent (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    parent_id BIGINT UNSIGNED NOT NULL,
    is_primary_contact BOOLEAN DEFAULT FALSE COMMENT 'Contact principal',
    is_financial_responsible BOOLEAN DEFAULT FALSE COMMENT 'Responsable financier',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES parents(id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_parent (student_id, parent_id)
);
```

### 2.4 `class_enrollments` (Inscriptions en Classe)
```sql
CREATE TABLE class_enrollments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    enrollment_date DATE NOT NULL,
    enrollment_type ENUM('Nouvelle','Réinscription','Transfert') DEFAULT 'Nouvelle',
    previous_school VARCHAR(255) NULL COMMENT 'Si transfert',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE RESTRICT,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_enrollment (student_id, academic_year_id),
    INDEX idx_class (class_id, academic_year_id)
);
```

### 2.5 `student_status_history` (Historique des Statuts)
```sql
CREATE TABLE student_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    old_status ENUM('Actif','Transféré','Exclu','Diplômé','Redoublant') NOT NULL,
    new_status ENUM('Actif','Transféré','Exclu','Diplômé','Redoublant') NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    changed_at TIMESTAMP NOT NULL,
    comment TEXT NULL COMMENT 'Raison du changement',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE RESTRICT
);
```

---

## Module 3 : Notes & Évaluations

### 3.1 `evaluations` (Évaluations)
```sql
CREATE TABLE evaluations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    subject_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL COMMENT 'Enseignant responsable',
    type ENUM('Devoir','Interrogation','Composition','TP') NOT NULL,
    name VARCHAR(255) NOT NULL COMMENT 'ex: Devoir 1, Composition S1',
    max_score DECIMAL(4,2) DEFAULT 20.00,
    date DATE NULL,
    is_published BOOLEAN DEFAULT FALSE COMMENT 'Notes publiées aux élèves',
    weight DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Poids dans le calcul de la moyenne (ex: composition = 2)',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_class_semester (class_id, semester_id),
    INDEX idx_teacher (teacher_id)
);
```

### 3.2 `grades` (Notes des Élèves)
```sql
CREATE TABLE grades (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    evaluation_id BIGINT UNSIGNED NOT NULL,
    score DECIMAL(4,2) NULL COMMENT 'Note /20 - NULL si absent',
    is_absent BOOLEAN DEFAULT FALSE COMMENT 'ABS',
    entered_by BIGINT UNSIGNED NOT NULL COMMENT 'Enseignant',
    entered_at TIMESTAMP NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (evaluation_id) REFERENCES evaluations(id) ON DELETE CASCADE,
    FOREIGN KEY (entered_by) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_grade (student_id, evaluation_id)
);
```

### 3.3 `subject_semester_averages` (Moyennes par Matière par Semestre)
```sql
CREATE TABLE subject_semester_averages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    average DECIMAL(4,2) NOT NULL COMMENT 'Moyenne matière /20',
    rank_in_class SMALLINT UNSIGNED NULL COMMENT 'Rang dans la classe pour cette matière',
    teacher_appreciation TEXT NULL COMMENT 'Appréciation de l enseignant',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_avg (student_id, subject_id, class_id, semester_id),
    INDEX idx_class_semester (class_id, semester_id)
);
```

### 3.4 `semester_report_cards` (Bulletins Semestriels - Résultats Globaux)
```sql
CREATE TABLE semester_report_cards (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    general_average DECIMAL(4,2) NOT NULL COMMENT 'Moyenne générale pondérée /20',
    rank_in_class SMALLINT UNSIGNED NOT NULL COMMENT 'Rang dans la classe',
    total_students SMALLINT UNSIGNED NOT NULL COMMENT 'Effectif de la classe',
    class_average DECIMAL(4,2) NULL COMMENT 'Moyenne de la classe',
    class_highest DECIMAL(4,2) NULL COMMENT 'Plus haute moyenne de la classe',
    class_lowest DECIMAL(4,2) NULL COMMENT 'Plus basse moyenne de la classe',
    mention ENUM('Félicitations','Tableau d honneur','Encouragements','Avertissement travail','Avertissement conduite','Blâme') NULL,
    general_appreciation TEXT NULL COMMENT 'Appréciation du conseil de classe',
    total_absences_hours SMALLINT UNSIGNED DEFAULT 0,
    total_justified_absences SMALLINT UNSIGNED DEFAULT 0,
    is_finalized BOOLEAN DEFAULT FALSE,
    finalized_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    UNIQUE KEY unique_report (student_id, class_id, semester_id),
    INDEX idx_class_semester (class_id, semester_id)
);
```

### 3.5 `grading_scales` (Barèmes Configurables)
```sql
CREATE TABLE grading_scales (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL COMMENT 'ex: Tableau d honneur',
    type ENUM('Mention','Decision') NOT NULL,
    min_average DECIMAL(4,2) NOT NULL COMMENT 'Moyenne min requise',
    max_absences SMALLINT UNSIGNED NULL COMMENT 'Max absences non justifiées tolérées',
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## Module 4 : Conseil de Classe

### 4.1 `class_councils` (Sessions de Conseil de Classe)
```sql
CREATE TABLE class_councils (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    council_date DATE NOT NULL,
    president_id BIGINT UNSIGNED NOT NULL COMMENT 'Président du conseil - FK -> users.id',
    status ENUM('Planifié','En cours','Terminé') DEFAULT 'Planifié',
    class_average DECIMAL(4,2) NULL,
    pass_rate DECIMAL(5,2) NULL COMMENT 'Taux de réussite %',
    general_observations TEXT NULL,
    minutes_pdf_path VARCHAR(255) NULL COMMENT 'PV du conseil en PDF',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    FOREIGN KEY (president_id) REFERENCES users(id) ON DELETE RESTRICT,
    UNIQUE KEY unique_council (class_id, semester_id)
);
```

### 4.2 `council_decisions` (Décisions par Élève)
```sql
CREATE TABLE council_decisions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_council_id BIGINT UNSIGNED NOT NULL,
    student_id BIGINT UNSIGNED NOT NULL,
    decision ENUM('Passage','Redoublement','Exclusion','Passage conditionnel','Orientation') NULL COMMENT 'Décision fin d année',
    mention ENUM('Félicitations','Tableau d honneur','Encouragements','Avertissement travail','Avertissement conduite','Blâme') NULL,
    appreciation TEXT NULL COMMENT 'Appréciation du conseil',
    next_class_id BIGINT UNSIGNED NULL COMMENT 'Classe de destination (si passage)',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (class_council_id) REFERENCES class_councils(id) ON DELETE CASCADE,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (next_class_id) REFERENCES classes(id) ON DELETE SET NULL,
    UNIQUE KEY unique_decision (class_council_id, student_id)
);
```

### 4.3 `council_attendees` (Participants au Conseil)
```sql
CREATE TABLE council_attendees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_council_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(100) NOT NULL COMMENT 'ex: Président, Secrétaire, Enseignant Maths',
    is_present BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (class_council_id) REFERENCES class_councils(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_attendee (class_council_id, user_id)
);
```

---

## Module 5 : Documents Officiels

### 5.1 `generated_documents` (Historique Documents Générés)
```sql
CREATE TABLE generated_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NULL COMMENT 'Si doc élève',
    user_id BIGINT UNSIGNED NULL COMMENT 'Si doc personnel (bulletin paie)',
    document_type ENUM(
        'BulletinSemestriel',
        'BulletinAnnuel',
        'AttestationScolarite',
        'AttestationInscription',
        'CertificatScolarite',
        'Exeat',
        'CarteScolaire',
        'ReleveNotes',
        'RecuPaiement',
        'BulletinPaie',
        'PVConseil'
    ) NOT NULL,
    document_pdf_path VARCHAR(255) NOT NULL,
    generated_by BIGINT UNSIGNED NOT NULL COMMENT 'Utilisateur générateur',
    generated_at TIMESTAMP NOT NULL,
    reference_number VARCHAR(100) UNIQUE NULL COMMENT 'Numéro unique document',
    semester_id BIGINT UNSIGNED NULL,
    academic_year_id BIGINT UNSIGNED NULL,
    metadata JSON NULL COMMENT 'Métadonnées additionnelles',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE SET NULL,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE SET NULL,
    INDEX idx_student_type (student_id, document_type),
    INDEX idx_reference (reference_number)
);
```

---

## Module 6 : Présences & Absences

### 6.1 `attendances` (Présence/Absence par Séance)
```sql
CREATE TABLE attendances (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    timetable_slot_id BIGINT UNSIGNED NULL COMMENT 'Lien optionnel vers EDT',
    date DATE NOT NULL COMMENT 'Date de la séance',
    status ENUM('Présent','Absent','Retard','Excusé') NOT NULL,
    hours TINYINT UNSIGNED DEFAULT 1 COMMENT 'Nombre d heures de la séance',
    marked_by BIGINT UNSIGNED NOT NULL COMMENT 'Enseignant',
    marked_at TIMESTAMP NOT NULL,
    justification_reason TEXT NULL,
    justification_document VARCHAR(255) NULL COMMENT 'Fichier justificatif',
    justified_by BIGINT UNSIGNED NULL COMMENT 'Admin validateur',
    justified_at TIMESTAMP NULL,
    parent_notified BOOLEAN DEFAULT FALSE,
    parent_notified_at TIMESTAMP NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (justified_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_attendance (student_id, subject_id, date, timetable_slot_id),
    INDEX idx_class_date (class_id, date),
    INDEX idx_student_semester (student_id, date)
);
```

### 6.2 `absence_alert_thresholds` (Seuils d'Alerte Absences)
```sql
CREATE TABLE absence_alert_thresholds (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    threshold_hours SMALLINT UNSIGNED NOT NULL COMMENT 'Nb heures non justifiées déclenchant alerte',
    action ENUM('NotifyParent','NotifyDirection','NotifyBoth') NOT NULL,
    description VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
```

---

## Module 7 : Discipline

### 7.1 `disciplinary_incidents` (Incidents Disciplinaires)
```sql
CREATE TABLE disciplinary_incidents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    reported_by BIGINT UNSIGNED NOT NULL COMMENT 'Enseignant/Surveillant rapporteur',
    incident_date DATE NOT NULL,
    incident_time TIME NULL,
    location VARCHAR(255) NULL COMMENT 'ex: En classe, Cour, etc.',
    description TEXT NOT NULL,
    severity ENUM('Mineur','Modéré','Grave','Très grave') NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_student (student_id),
    INDEX idx_date (incident_date)
);
```

### 7.2 `disciplinary_sanctions` (Sanctions)
```sql
CREATE TABLE disciplinary_sanctions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    incident_id BIGINT UNSIGNED NULL COMMENT 'Lien incident optionnel',
    student_id BIGINT UNSIGNED NOT NULL,
    sanction_type ENUM(
        'Avertissement verbal',
        'Avertissement écrit',
        'Blâme',
        'Exclusion temporaire',
        'Exclusion définitive',
        'Retenue',
        'Travail supplémentaire'
    ) NOT NULL,
    issued_by BIGINT UNSIGNED NOT NULL COMMENT 'Personne qui prononce la sanction',
    sanction_date DATE NOT NULL,
    duration_days TINYINT UNSIGNED NULL COMMENT 'Durée exclusion (1-8 jours)',
    start_date DATE NULL COMMENT 'Début exclusion',
    end_date DATE NULL COMMENT 'Fin exclusion',
    reason TEXT NOT NULL,
    parent_notified BOOLEAN DEFAULT FALSE,
    parent_notified_at TIMESTAMP NULL,
    parent_acknowledged BOOLEAN DEFAULT FALSE COMMENT 'Parent a pris connaissance',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (incident_id) REFERENCES disciplinary_incidents(id) ON DELETE SET NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_student (student_id),
    INDEX idx_type (sanction_type)
);
```

### 7.3 `disciplinary_councils` (Conseils de Discipline)
```sql
CREATE TABLE disciplinary_councils (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    council_date DATE NOT NULL,
    president_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    reason TEXT NOT NULL,
    decision ENUM(
        'Avertissement solennel',
        'Exclusion temporaire',
        'Exclusion définitive',
        'Sursis',
        'Classement sans suite'
    ) NULL,
    decision_details TEXT NULL,
    minutes_pdf_path VARCHAR(255) NULL COMMENT 'PV du conseil',
    status ENUM('Convoqué','Tenu','Annulé') DEFAULT 'Convoqué',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (president_id) REFERENCES users(id) ON DELETE RESTRICT
);
```

### 7.4 `disciplinary_council_members` (Membres du Conseil de Discipline)
```sql
CREATE TABLE disciplinary_council_members (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    disciplinary_council_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    role VARCHAR(100) NOT NULL COMMENT 'ex: Président, Secrétaire, Membre',
    is_present BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (disciplinary_council_id) REFERENCES disciplinary_councils(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

---

## Module 8 : Emplois du Temps

### 8.1 `rooms` (Salles de Cours)
```sql
CREATE TABLE rooms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ex: S01, Labo1',
    name VARCHAR(255) NOT NULL,
    capacity SMALLINT UNSIGNED NOT NULL COMMENT 'Nb places',
    type ENUM('Classe','Labo','Salle info','Salle sport','Autre') DEFAULT 'Classe',
    building VARCHAR(255) NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL
);
```

### 8.2 `timetable_slots` (Séances d'Emploi du Temps)
```sql
CREATE TABLE timetable_slots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    class_id BIGINT UNSIGNED NOT NULL,
    subject_id BIGINT UNSIGNED NOT NULL,
    teacher_id BIGINT UNSIGNED NOT NULL,
    room_id BIGINT UNSIGNED NULL,
    semester_id BIGINT UNSIGNED NOT NULL,
    day_of_week ENUM('Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    is_recurring BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE SET NULL,
    FOREIGN KEY (semester_id) REFERENCES semesters(id) ON DELETE CASCADE,
    INDEX idx_conflict_detection (day_of_week, start_time, end_time),
    INDEX idx_teacher_timetable (teacher_id, semester_id),
    INDEX idx_class_timetable (class_id, semester_id)
);
```

---

## Module 9 : Comptabilité & Finances

### 9.1 `fee_types` (Types de Frais)
```sql
CREATE TABLE fee_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL COMMENT 'ex: SCOL, INSC, APE, CANT',
    name VARCHAR(255) NOT NULL COMMENT 'ex: Frais de scolarité, Contribution APE',
    default_amount DECIMAL(10,2) NOT NULL,
    is_mandatory BOOLEAN DEFAULT TRUE,
    applies_to_level_id BIGINT UNSIGNED NULL COMMENT 'NULL = tous niveaux',
    description TEXT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (applies_to_level_id) REFERENCES levels(id) ON DELETE SET NULL
);
```

### 9.2 `student_fees` (Frais Attribués aux Élèves)
```sql
CREATE TABLE student_fees (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    fee_type_id BIGINT UNSIGNED NOT NULL,
    academic_year_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL COMMENT 'Montant dû',
    amount_paid DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Montant payé',
    discount DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Bourse/Exonération',
    status ENUM('Impayé','Partiel','Payé','Exonéré') DEFAULT 'Impayé',
    due_date DATE NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (fee_type_id) REFERENCES fee_types(id) ON DELETE RESTRICT,
    FOREIGN KEY (academic_year_id) REFERENCES academic_years(id) ON DELETE CASCADE,
    UNIQUE KEY unique_fee (student_id, fee_type_id, academic_year_id),
    INDEX idx_status (status)
);
```

### 9.3 `student_payments` (Paiements)
```sql
CREATE TABLE student_payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_id BIGINT UNSIGNED NOT NULL,
    student_fee_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    payment_method ENUM('Espèces','Virement','Mobile','Chèque') NOT NULL,
    reference VARCHAR(100) NULL COMMENT 'Référence paiement',
    received_by BIGINT UNSIGNED NOT NULL COMMENT 'Caissier',
    payment_date DATE NOT NULL,
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE RESTRICT,
    INDEX idx_receipt (receipt_number)
);
```

### 9.4 `expenses` (Dépenses de l'Établissement)
```sql
CREATE TABLE expenses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category VARCHAR(100) NOT NULL COMMENT 'ex: Fournitures, Maintenance, Salaires',
    description TEXT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    expense_date DATE NOT NULL,
    reference VARCHAR(100) NULL,
    justification_path VARCHAR(255) NULL COMMENT 'Fichier justificatif',
    recorded_by BIGINT UNSIGNED NOT NULL,
    approved_by BIGINT UNSIGNED NULL,
    status ENUM('Enregistrée','Approuvée','Rejetée') DEFAULT 'Enregistrée',
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### 9.5 `payment_schedules` (Échéanciers de Paiement)
```sql
CREATE TABLE payment_schedules (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    student_fee_id BIGINT UNSIGNED NOT NULL,
    installment_number TINYINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    due_date DATE NOT NULL,
    is_paid BOOLEAN DEFAULT FALSE,
    paid_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (student_fee_id) REFERENCES student_fees(id) ON DELETE CASCADE
);
```

---

## Module 10 : Paie Personnel

### 10.1 `staff_contracts` (Contrats Personnel)
```sql
CREATE TABLE staff_contracts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL COMMENT 'FK -> users.id',
    contract_type ENUM('Permanent','Vacataire','Contractuel') NOT NULL,
    position VARCHAR(255) NOT NULL COMMENT 'Enseignant, Surveillant, Admin, etc.',
    base_salary DECIMAL(10,2) NULL COMMENT 'Salaire fixe mensuel',
    hourly_rate DECIMAL(8,2) NULL COMMENT 'Taux horaire (vacataires)',
    start_date DATE NOT NULL,
    end_date DATE NULL COMMENT 'Si temporaire/vacataire',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    deleted_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### 10.2 `payroll_records` (Fiches de Paie)
```sql
CREATE TABLE payroll_records (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_contract_id BIGINT UNSIGNED NOT NULL,
    period_month TINYINT UNSIGNED NOT NULL COMMENT '1-12',
    period_year YEAR NOT NULL,
    hours_worked DECIMAL(6,2) NULL COMMENT 'Si vacataire',
    gross_salary DECIMAL(10,2) NOT NULL,
    deductions DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Retenues',
    bonuses DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Primes',
    net_salary DECIMAL(10,2) NOT NULL,
    payment_date DATE NULL,
    status ENUM('Brouillon','Approuvé','Payé') DEFAULT 'Brouillon',
    bulletin_pdf_path VARCHAR(255) NULL,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL,
    FOREIGN KEY (staff_contract_id) REFERENCES staff_contracts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_payroll (staff_contract_id, period_month, period_year)
);
```

---

[Suivant : Architecture des Composants →](./components.md)
