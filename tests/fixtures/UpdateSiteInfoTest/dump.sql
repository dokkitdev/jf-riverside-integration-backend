INSERT INTO request_logs (id, destination, url, method, request_body, request_headers, response_status_code, response_body, response_headers, created_at, updated_at) VALUES
    (1, 'self', 'http://localhost/jobs', 'POST', '', '', 200, '', '', '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO jobs (id, order_no, simpro_job_id, is_appointment_sent, request_log_id, created_at, updated_at) VALUES
    (1, 'job_1', 100, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
    (2, 'job_2', 101, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
    (3, 'job_3', 102, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
    (4, 'job_4', 103, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
    (5, 'job_5', null, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
    (6, 'job_6', 104, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
    (7, 'job_7', 105, false, 1, '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO job_operations (id, job_id, operation, status, details, created_at, updated_at) VALUES
    (1, 1, 'receive_data_from_riverside', 'success', null, '2018-10-10 10:10:10', '2018-10-10 10:10:10');
