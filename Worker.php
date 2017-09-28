<?php
/**
 * Worker 
 * 
 * @author leonhou <vleonhou@qq.com> 
 */
class Worker
{
    /**
     * task 
     * 
     * @var mixed
     */
    private $task = NULL;

    /**
     * config 
     * 
     * @var mixed
     */
    private $config = NULL;

    /**
     * __construct 
     * 
     * 
     * @return void
     */
    public function __construct($config,$task)
    {
        $this->task = $task;
        $this->config = $config;
    }

    /**
     * run 
     * 
     * 
     * @return void
     */
    public function run()
    {
        Task::setConfig($this->config);
        foreach ($this->task as $task) {
            $pid = pcntl_fork();
            if( $pid == 0 )
            {
                $task = new Task($task);
                $task->run();
            }
            else if( $pid > 0 ) {
                continue;
            }
            else {
                throw new Exception("fork 进程失败");
            }
        }
    }
}


