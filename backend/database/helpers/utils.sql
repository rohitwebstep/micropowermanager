==== Duplicate Meters
SELECT 
    p.id AS "Person ID",
    p.national_id_number AS "National ID Number",
    CONCAT(p.name, ' ', p.surname) AS "Full Name",
    COUNT(DISTINCT d.device_id) AS "Total Meters",
    GROUP_CONCAT(DISTINCT m.serial_number SEPARATOR ', ') AS "Meter Serial Numbers",
    GROUP_CONCAT(DISTINCT a.phone SEPARATOR ', ') AS "Contact Phone Numbers"
FROM people p
INNER JOIN devices d 
    ON d.person_id = p.id AND d.device_type = 'meter'
INNER JOIN meters m 
    ON d.device_id = m.id
LEFT JOIN addresses a 
    ON a.owner_id = p.id AND a.owner_type = 'people'
GROUP BY 
    p.id, 
    p.national_id_number, 
    p.name, 
    p.surname
HAVING 
    COUNT(DISTINCT d.device_id) > 1
ORDER BY 
    "Total Meters" DESC, p.id;