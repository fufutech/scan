<?php
declare(strict_types=1);

namespace App\Process;

use App\Exception\ApiException;
use App\Lib\Factory;
use App\Model\DownloadTaskModel;
use Hyperf\Process\AbstractProcess;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class DownloadTaskProcess extends AbstractProcess
{
    /**
     * 进程数量
     *
     * @var int
     */
    public $nums = 1;

    /**
     * 进程名称
     *
     * @var string
     */
    public $name = 'process-download-task';

    /**
     * 重定向自定义进程的标准输入和输出
     *
     * @var bool
     */
    public $redirectStdinStdout = false;

    /**
     * 管道类型
     *
     * @var int
     */
    public $pipeType = 2;

    /**
     * 是否启用协程
     *
     * @var bool
     */
    public $enableCoroutine = true;

    private $spreadsheet;
    private $sheet;

    private array $taskData = [];

    public function handle(): void
    {
        $redis_key = 'queues:DownloadTask';
        $redis     = $this->container->get(\Redis::class);
        while (true) {
            $taskSynData = $redis->rPop($redis_key);
//            echo "pop 数据：" . json_encode($taskSynData) . PHP_EOL;
            if ($taskSynData) {
                try {
                    $taskSynData = json_decode($taskSynData, true);

                    $task_id = $taskSynData['task_id'] ?? 0;
                    if (!$task_id) {
                        throw new ApiException(
                            '导出数据存在问题 task_id ：'.$task_id
                        );
                    }

                    $taskData = DownloadTaskModel::where('task_id', $task_id)
                        ->where('status', 0)->first();
                    if (!$taskData) {
                        throw new ApiException('下载任务不存在');
                    }
                    $this->taskData = $taskData->toArray(); // 任务数据

                    $ClassPath         = 'App\Lib\DownloadTask\\'
                        .$taskSynData['mode'];
                    $ClassPath         = str_replace('/', '', $ClassPath);
                    $FactoryObject     = Factory::getInstance();
                    $OrderSyn          = $FactoryObject->Get($ClassPath);
                    $page              = 1;
                    $this->spreadsheet = new Spreadsheet();
                    $this->sheet       = $this->spreadsheet->getActiveSheet();

                    $this->handleData($OrderSyn, $taskSynData, $page);

                    $this->saveExcel();
                } catch (\Throwable $throwable) {
                    if (isset($task_id) && $task_id) {
                        DownloadTaskModel::where('task_id', $task_id)->whereIn(
                            'status', [0, 1]
                        )->update([
                            'status' => 3, // 失败
                            'desc'   => $throwable->getMessage(),
                        ]);
                    }
                    echo '导出报错：'.$throwable->getMessage().PHP_EOL;
                }
            } else {
                sleep(1); // 不需要频繁查看动态号池（Redis）数量
            }
        }
    }

    public function handleData($OrderSyn, $taskSynData, $page)
    {
        $HandleData = $OrderSyn::Handle($taskSynData, $page);
        if ($HandleData && $HandleData['status'] == 200) {
            $lastPage = $HandleData['lastPage'];
            $this->exportWrite($taskSynData, $HandleData['data'], $page);

            if ($page <= $lastPage) {
                $page++;
                $this->handleData($OrderSyn, $taskSynData, $page);
            }
        } else {
            if ($page == 1) {
                throw new ApiException($HandleData['message']);
            }
        }
    }

    public function exportWrite($taskSynData, $HandleData, $page)
    {
        $headerArr = $taskSynData['header'];

        // 设置表头
        $columnIndex = 1;
        foreach ($headerArr as $header) {
            $this->sheet->setCellValueByColumnAndRow(
                $columnIndex, 1, $header['name']
            );
            $columnIndex++;
        }
        // 填充数据
        $rowIndex = ($page - 1) * 100 + 2;
        foreach ($HandleData as $row) {
            $columnIndex = 1;
            foreach ($headerArr as $header) {
                $field     = $header['field'];
                $cellValue = isset($row[$field]) ? $row[$field] : '';
                $this->sheet->setCellValueByColumnAndRow(
                    $columnIndex, $rowIndex, $cellValue
                );
                $columnIndex++;
            }
            $rowIndex++;
        }
    }

    //获取文件的大小
    public function formatBytes($bytes, $precision = 2): string
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        $bytes = max($bytes, 0);
        $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow   = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision).$units[$pow];
    }

    private function saveExcel(): void
    {
        // 保存文件
        $writer = new Xlsx($this->spreadsheet);

        $datePath = '/data/DownloadTask/'.date('Ymd').'/';
        $filePath = $datePath.$this->taskData['file_name'].'.xls';
        $path     = './runtime'.$datePath;
        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }
        $savePath = './runtime'.$filePath;
        $writer->save($savePath);
        $size = $this->formatBytes(filesize($savePath));
        // 生成成功
        DownloadTaskModel::where('task_id', $this->taskData['task_id'])->update(
            [
                'path'      => $filePath,
                'size'      => $size,
                'status'    => 2,
                'over_time' => time(),
            ]
        );
    }
}