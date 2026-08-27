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


==== Missing Meters
SELECT t.serial_number
FROM (
  SELECT '47001877043' AS serial_number
  UNION ALL SELECT '47001878371'
) t
LEFT JOIN meters mts 
  ON t.serial_number = mts.serial_number
WHERE mts.serial_number IS NULL;


==== Missing Orders
SELECT t.token
FROM (
  SELECT '4414 1196 0115 2770 1258' AS token
  UNION ALL SELECT '3569 5547 5102 2150 8411'
) t
LEFT JOIN orders o 
  ON t.token = o.token
WHERE o.token IS NULL;