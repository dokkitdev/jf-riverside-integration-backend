INSERT INTO request_logs (id, destination, url, method, request_body, request_headers, response_status_code, response_body, response_headers, created_at, updated_at) VALUES
(1, 'self', 'http://localhost/jobs', 'POST', '', '', 200, '', '', '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO jobs (id, order_no, simpro_job_id, is_appointment_sent, created_at, updated_at) VALUES
(2, 'test_job', 555, false, '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO job_operations (id, job_id, operation, status, details, created_at, updated_at) VALUES
(3, 2, 'create', 'success', null, '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO job_operation_request_log (id, job_operation_id, request_log_id) VALUES
(4, 3, 1);