INSERT INTO deductions (code, name, type, value, is_mandatory, is_active)
SELECT 'SSS', 'SSS Contribution', 'fixed', 0, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM deductions WHERE code = 'SSS');

INSERT INTO deductions (code, name, type, value, is_mandatory, is_active)
SELECT 'PHILHEALTH', 'PhilHealth Contribution', 'fixed', 0, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM deductions WHERE code = 'PHILHEALTH');

INSERT INTO deductions (code, name, type, value, is_mandatory, is_active)
SELECT 'PAGIBIG', 'Pag-IBIG Contribution', 'fixed', 0, 1, 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM deductions WHERE code = 'PAGIBIG');

INSERT INTO employee_deductions (employee_id, deduction_id, amount, effective_from, effective_to, is_active)
SELECT e.id, d.id, NULL, COALESCE(e.date_hired, CURDATE()), NULL, 1
FROM employees e
JOIN deductions d ON d.code IN ('SSS', 'PHILHEALTH', 'PAGIBIG')
WHERE e.is_active = 1
  AND NOT EXISTS (SELECT 1 FROM employee_deductions ed
                  WHERE ed.employee_id = e.id AND ed.deduction_id = d.id);