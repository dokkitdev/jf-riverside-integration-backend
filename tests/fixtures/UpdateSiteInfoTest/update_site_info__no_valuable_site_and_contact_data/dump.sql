INSERT INTO jobs (id, order_no, simpro_job_id, is_appointment_sent, created_at, updated_at) VALUES
(3, 'job_2', 101, false, '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO job_operations (id, job_id, operation, status, details, created_at, updated_at) VALUES
(4, 3, 'update_site_info', 'in_progress', null, '2018-10-10 10:10:10', '2018-10-10 10:10:10');
