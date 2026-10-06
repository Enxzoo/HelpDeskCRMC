INSERT INTO offices (office_name, description, is_active)
SELECT wanted.office_name, wanted.description, 1
FROM (
    SELECT 'SASO' AS office_name, 'Handles student affairs and student support concerns' AS description
    UNION ALL SELECT 'CTE', 'College of Teacher Education'
    UNION ALL SELECT 'CBE', 'College of Business Education'
    UNION ALL SELECT 'CCS', 'College of Computer Studies'
    UNION ALL SELECT 'CCJE', 'College of Justice Education'
    UNION ALL SELECT 'Psychology Department', 'Handles Psychology Department concerns'
    UNION ALL SELECT 'Main Office', 'Handles general office concerns'
) AS wanted
LEFT JOIN offices existing ON existing.office_name = wanted.office_name
WHERE existing.office_id IS NULL;
