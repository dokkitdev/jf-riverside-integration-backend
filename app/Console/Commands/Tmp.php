<?php

namespace App\Console\Commands;

use App\Resources\SimproApi\SimproWebhookResource;
use App\Services\JobOperationService;
use App\Services\JobsFlowService;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class Tmp extends Command
{
    protected $signature = 'tmp';

    public function handle4()
    {
        $data = DB::table('job_operations')
            ->leftJoin('jobs', 'jobs.id', '=', 'job_operations.job_id')
            ->where('jobs.simpro_job_id', '397030')
            ->whereExists(function (Builder $query) {
                $query
                    ->from('request_logs', 'sub_request_logs')
                    ->leftJoin(DB::raw('job_operation_request_log sub_job_operation_request_log'), 'sub_request_logs.id', '=', 'sub_job_operation_request_log.request_log_id')
                    ->where('sub_job_operation_request_log.job_operation_id', DB::raw('job_operations.id'))
                    ->where(DB::raw('lower(sub_request_logs.response_body)'), 'LIKE', '%unable to run web object%');
            })
            ->get()
            ->toArray();

        $failed = [];

        foreach ($data as $item) {
            $item = (array) $item;

            if ($item['status'] !== 'success') {
                $failed[] = $item;
            }
        }

        dump($failed);
    }

    public function handle()
    {
        $data = DB::table('job_operations')
            ->select(['*', DB::raw('job_operations.id as operation_id')])
            ->leftJoin('job_operation_request_log', 'job_operations.id', '=', 'job_operation_request_log.job_operation_id')
            ->leftJoin('request_logs', 'request_logs.id', '=', 'job_operation_request_log.request_log_id')
            ->leftJoin('jobs', 'jobs.id', '=', 'job_operations.job_id')
            ->where('request_logs.url', 'https://jfriversideintegration.co.uk/simpro-webhook')
            ->whereExists(function (Builder $query) {
                $query
                    ->from('request_logs', 'sub_request_logs')
                    ->leftJoin(DB::raw('job_operation_request_log sub_job_operation_request_log'), 'sub_request_logs.id', '=', 'sub_job_operation_request_log.request_log_id')
                    ->where('sub_job_operation_request_log.job_operation_id', DB::raw('job_operations.id'))
                    ->where(DB::raw('lower(sub_request_logs.response_body)'), 'LIKE', '%unable to run web object%');
            })
            ->orderBy('job_operations.id')
            ->get()
            ->toArray();

        $jobIds = [];
        $operations = [];

        foreach ($data as $item) {
            $item = (array) $item;

            if (in_array($item['simpro_job_id'], $jobIds)) {
                continue;
            }

            $jobIds[] = $item['simpro_job_id'];

            $operations[] = [
                'webhook' => json_decode($item['request_body'], true),
                'simpro_job_id' => $item['simpro_job_id'],
                'order_no' => $item['order_no'],
                'operation_id' => $item['operation_id']
            ];
        }

        foreach ($operations as $operation) {
            echo "{$operation['order_no']} -> {$operation['simpro_job_id']}" . PHP_EOL;

            $jobOperation = app(JobOperationService::class)->find($operation['operation_id']);

            app(JobsFlowService::class)->initSimproWebhookProcessing(
                SimproWebhookResource::makeFromSimproRequestData($operation['webhook']),
                $jobOperation
            );

            dump($operation);

            break;
        }

        dump(count($operations));
    }

    public function handle2()
    {
        $webhook = json_decode(
            '{"ID":"job.stage.complete","build":"gassure.simprocloud.com","description":"Stage of Job #402491 has been moved from Progress to Complete","name":"Job","action":"stage.complete","reference":{"companyID":0,"jobID":402491,"stage":"complete"},"date_triggered":"2023-06-30T07:41:31+01:00"}',
            true
        );

        $jobOperation = app(JobOperationService::class)->find(29715);

        app(JobsFlowService::class)->initSimproWebhookProcessing(
            SimproWebhookResource::makeFromSimproRequestData($webhook),
            $jobOperation
        );
    }
}

