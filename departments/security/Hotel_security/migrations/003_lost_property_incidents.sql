ALTER TABLE incidents
  ADD COLUMN property_report_type VARCHAR(100) NULL AFTER item_desc,
  ADD COLUMN contact VARCHAR(100) NULL AFTER property_report_type,
  ADD COLUMN legacy_lost_property_id INT NULL,
  ADD UNIQUE KEY uq_incidents_legacy_lost_property_id (legacy_lost_property_id);

INSERT INTO incidents
  (title, description, location, severity, status, reported_by, created_at, category,
   resolved_at, room_no, involved, item_desc, incident_at, property_report_type,
   contact, legacy_lost_property_id)
SELECT
  LEFT(CONCAT(lp.kind, ' - Lost / Found Property - ', COALESCE(lp.location, 'Location unknown')), 150),
  NULLIF(lp.description, ''),
  lp.location,
  'low',
  CASE
    WHEN lp.status IN ('Returned', 'Closed') THEN 'resolved'
    WHEN lp.status = 'Reported' THEN 'open'
    ELSE 'investigating'
  END,
  lp.reported_by,
  lp.opened_at,
  'Lost / Found Property',
  CASE WHEN lp.status IN ('Returned', 'Closed') THEN COALESCE(lp.closed_at, lp.opened_at) ELSE NULL END,
  LEFT(NULLIF(lp.room_no, ''), 10),
  NULLIF(lp.owner_name, ''),
  LEFT(NULLIF(lp.description, ''), 200),
  lp.opened_at,
  lp.kind,
  NULLIF(lp.contact, ''),
  lp.id
FROM lost_property lp
WHERE NOT EXISTS (
  SELECT 1 FROM incidents existing
  WHERE existing.legacy_lost_property_id = lp.id
);

INSERT INTO incident_updates (incident_id, user_id, status, note, created_at)
SELECT i.id, lp.reported_by,
       CASE
         WHEN lp.status IN ('Returned', 'Closed') THEN 'resolved'
         WHEN lp.status = 'Reported' THEN 'open'
         ELSE 'investigating'
       END,
       CONCAT('Imported from Lost Property record #', lp.id, '. Previous status: ', lp.status),
       lp.opened_at
FROM lost_property lp
JOIN incidents i ON i.legacy_lost_property_id = lp.id
WHERE NOT EXISTS (
  SELECT 1 FROM incident_updates existing
  WHERE existing.incident_id = i.id
    AND existing.note LIKE CONCAT('Imported from Lost Property record #', lp.id, '.%')
);

INSERT INTO incident_updates (incident_id, user_id, status, note, created_at)
SELECT i.id, n.user_id,
       CASE
         WHEN n.status IN ('Returned', 'Closed') THEN 'resolved'
         WHEN n.status = 'Reported' THEN 'open'
         ELSE 'investigating'
       END,
       LEFT(CONCAT(
         IF(n.agency IS NOT NULL AND n.agency != '', CONCAT(n.agency, ': '), ''),
         COALESCE(n.note, ''),
         ' [Imported lost-property update #', n.id, ']'
       ), 500),
       n.created_at
FROM case_notes n
JOIN incidents i ON i.legacy_lost_property_id = n.ref_id
WHERE n.ref_type = 'lost-property'
  AND NOT EXISTS (
    SELECT 1 FROM incident_updates existing
    WHERE existing.incident_id = i.id
      AND existing.note LIKE CONCAT('%[Imported lost-property update #', n.id, ']')
  );
