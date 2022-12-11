INSERT INTO request_logs (id, destination, url, method, request_body, request_headers, response_status_code, response_body, response_headers, created_at, updated_at) VALUES
  (1, 'self', 'http://localhost/jobs', 'POST', 'previous-request-raw-body', '{"request_header_key": "request_header_value"}', 200, '', '{"response_header_key": "response_header_value"}', '2018-11-11 11:11:11', '2018-11-11 11:11:11'),
  (2, 'self', 'http://localhost/simpro-webhook', 'POST', 'previous-request-raw-body', '{"request_header_key": "request_header_value"}', 200, '', '{"response_header_key": "response_header_value"}', '2018-11-11 11:11:11', '2018-11-11 11:11:11'),
  (3, 'self', 'http://localhost/simpro-webhook', 'POST', 'previous-request-raw-body', '{"request_header_key": "request_header_value"}', 200, '', '{"response_header_key": "response_header_value"}', '2018-11-11 11:11:11', '2018-11-11 11:11:11');

INSERT INTO jobs (id, order_no, simpro_job_id, is_appointment_sent, created_at, updated_at) VALUES
  (1, 'test_job_no', null, false, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
  (2, 'job_for_webhooks', 7777, false, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
  (3, 'job_for_webhooks_appointment_sent', 7778, true, '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO job_operations (id, job_id, operation, status, details, created_at, updated_at) VALUES
  (1, 1, 'create', 'success', null, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
  (2, 1, 'create', 'success', null, '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
  (3, 3, 'handle_webhook', 'in_progress', '{"webhook_type":"any.type","webhook_description":"any description"}', '2018-10-10 10:10:10', '2018-10-10 10:10:10'),
  (4, 2, 'handle_webhook', 'in_progress', '{"webhook_type":"any.type","webhook_description":"any description"}', '2018-10-10 10:10:10', '2018-10-10 10:10:10');

INSERT INTO job_operation_request_log (id, job_operation_id, request_log_id) VALUES
  (1, 1, 1),
  (2, 1, 1),
  (3, 1, 1),
  (4, 3, 2),
  (5, 4, 3);
