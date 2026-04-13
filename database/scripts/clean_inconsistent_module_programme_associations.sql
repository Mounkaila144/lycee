-- ============================================================================
-- Script de Nettoyage des Associations Module ↔ Programme Incohérentes
-- ============================================================================
-- Date: 2026-01-14
-- Objectif: Supprimer les associations où le niveau du module ne correspond
--           pas aux niveaux du programme
-- ============================================================================

-- ÉTAPE 1: Identifier les associations incohérentes
-- ============================================================================
SELECT 
    mp.programme_id,
    p.code as programme_code,
    p.type,
    GROUP_CONCAT(DISTINCT pl.level ORDER BY pl.level) as programme_levels,
    m.id as module_id,
    m.code as module_code,
    m.level as module_level
FROM module_programs mp
JOIN programmes p ON mp.programme_id = p.id
LEFT JOIN program_levels pl ON p.id = pl.program_id
JOIN modules m ON mp.module_id = m.id
WHERE m.level NOT IN (
    SELECT level 
    FROM program_levels 
    WHERE program_id = p.id
)
GROUP BY mp.programme_id, p.code, p.type, m.id, m.code, m.level
ORDER BY mp.programme_id, m.code;

-- ÉTAPE 2: Compter le nombre d'associations incohérentes
-- ============================================================================
SELECT COUNT(*) as total_inconsistencies
FROM module_programs mp
JOIN programmes p ON mp.programme_id = p.id
JOIN modules m ON mp.module_id = m.id
WHERE m.level NOT IN (
    SELECT level 
    FROM program_levels pl
    WHERE pl.program_id = p.id
);

-- ÉTAPE 3: Supprimer les associations incohérentes
-- ============================================================================
-- ⚠️ ATTENTION: Cette opération est IRRÉVERSIBLE
-- ⚠️ Assurez-vous d'avoir une sauvegarde avant d'exécuter cette commande

DELETE mp FROM module_programs mp
JOIN programmes p ON mp.programme_id = p.id
JOIN modules m ON mp.module_id = m.id
WHERE m.level NOT IN (
    SELECT level 
    FROM program_levels pl
    WHERE pl.program_id = p.id
);

-- ÉTAPE 4: Vérifier qu'il n'y a plus d'incohérences
-- ============================================================================
-- Résultat attendu: 0
SELECT COUNT(*) as remaining_inconsistencies
FROM module_programs mp
JOIN programmes p ON mp.programme_id = p.id
JOIN modules m ON mp.module_id = m.id
WHERE m.level NOT IN (
    SELECT level 
    FROM program_levels pl
    WHERE pl.program_id = p.id
);

-- ÉTAPE 5: Vérifier l'intégrité des données après nettoyage
-- ============================================================================
-- Tous les modules associés doivent avoir un niveau compatible
SELECT 
    p.id as programme_id,
    p.code as programme_code,
    p.type,
    GROUP_CONCAT(DISTINCT pl.level ORDER BY pl.level) as programme_levels,
    COUNT(DISTINCT m.id) as total_modules,
    GROUP_CONCAT(DISTINCT m.level ORDER BY m.level) as module_levels
FROM programmes p
LEFT JOIN program_levels pl ON p.id = pl.program_id
LEFT JOIN module_programs mp ON p.id = mp.programme_id
LEFT JOIN modules m ON mp.module_id = m.id
GROUP BY p.id, p.code, p.type
HAVING total_modules > 0
ORDER BY p.code;

-- ============================================================================
-- NOTES D'UTILISATION
-- ============================================================================
-- 1. Exécuter d'abord l'ÉTAPE 1 pour voir les données problématiques
-- 2. Exécuter l'ÉTAPE 2 pour connaître le nombre d'associations à supprimer
-- 3. Faire une SAUVEGARDE de la base de données
-- 4. Exécuter l'ÉTAPE 3 pour supprimer les associations incohérentes
-- 5. Exécuter l'ÉTAPE 4 pour confirmer que tout est nettoyé
-- 6. Exécuter l'ÉTAPE 5 pour vérifier l'intégrité globale
-- ============================================================================
