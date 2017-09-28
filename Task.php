<?php
/**
 * Task 
 * 
 * @author leonhou <vleonhou@qq.com> 
 */
class Task
{
    /**
     * wokerlogFile 
     * @var string
     */
    private $wokerLogFile = "/tmp/huoche/logs/huoche.log";
    private $logFile = "/tmp/huoche/logs/huoche.log";

    private $conf = [];  //配置文件
    private $from_station_name;  //出发地
    private $from_station_code;  //出发地对应的代码
    private $to_station_name;  //目的地
    private $to_station_code;  //目的地对应的代码
    private $query_date;  //查询日期
    private $query_num = 1;  //统计查询次数
    private $trainList = [];  //查询车次
    private $ticketList = [];  //车站信息
    static private $mail;  //邮箱地址
    private $url;

    static private $debugMode = TRUE;
    static private $notifyTel;
    private $haveNotifyed = FALSE;
    private $isDay = TRUE;
    
    private $stations = [];  //全部站点信息
    private $ticketType= [
        '商务座' => '31',
        '特等座' => '31',
        '一等座' => '30',
        '二等座' => '29',
        '高级软卧' => 'gr_num',
        '软卧' => '22',
        '硬卧' => '27',
        '软座' => 'rz_num',
        '硬座' => '28',
        '无座' => '25',
        '其它' => 'qt_num'
    ];

    /**
     * __construct 
     * 
     * @param mixed $config config 
     * 
     * @return void
     */
    public function __construct($config)
    {
        if (  empty($config['from']) || 
            empty($config['to']) || 
            empty($config['date']) || 
            empty($config['houxuancheci']) || 
            empty($config['zuoweiLeixin'])
        )
        {
            $this->msg("缺少必要参数","Error");
        }
        $this->conf = require("config.php");
        $this->stations = $this->formatStationInfo($this->conf['stationInfo']);
        $this->checkStation($config['from']);
        $this->checkStation($config['to']);
        $this->from_station_name = $config['from'];
        $this->from_station_code = $this->stations[$this->from_station_name];
        $this->to_station_name = $config['to'];
        $this->to_station_code = $this->stations[$this->to_station_name];
        $this->query_date = $config['date'];
        $this->trainList = $this->getTrainList($config['houxuancheci']);
        $this->ticketList = $this->checkTicket($config['zuoweiLeixin']);

        is_dir("/tmp/huoche/logs") OR mkdir("/tmp/huoche/logs",0700,TRUE);
        touch($this->wokerLogFile);
        $logFile = "/tmp/huoche/logs/".$this->query_date.'-'.$this->from_station_code.'-'.$this->from_station_code.'.log';
        touch($logFile);
        $this->logFile = $logFile; 
        set_error_handler([$this,'errorHandler']);
        set_exception_handler([$this,'exceptionHandler']);
    }

    /**
     * getTrainList 
     * 
     * @param mixed $houxuancheci houxuancheci 
     * 
     * @return void
     */
    public function getTrainList($houxuancheci)
    {
        $trainArr = explode(',', $houxuancheci);
        $trainList = [];
        foreach ($trainArr as $train) {
            array_push($trainList,strtoupper($train));
        }
        return $trainList;
    }

    /**
     * exceptionHandler 
     * 
     * 
     * @return void
     */
    public function exceptionHandler($exception)
    {
        file_put_contents($this->wokerLogFile,date('Y-m-d H:i:s')."----> Exception:".$exception->getMessage()."\n",FILE_APPEND);
        file_put_contents($this->wokerLogFile,"进程id:".posix_getuid().$this->query_date.'从'.$this->from_station_name.'到'.$this->to_station_name."\n",FILE_APPEND);
    }

    /**
     * errorHandler 
     * 
     * @return void
     */
    public function errorHandler($severity, $message, $filepath, $line)
    {
        file_put_contents($this->wokerLogFile,date('Y-m-d H:i:s')."----> Error:".$severity.':'.$message.' '.$filepath.' '.$line."\n",FILE_APPEND);
        file_put_contents($this->wokerLogFile,"进程id:".posix_getuid()."\n",FILE_APPEND);
    }

    /**
     * setConfig 
     * 
     * @param  mixed $config
     * 
     * @return void
     */
    static public function setConfig($config)
    {
        isset($config['notifyEmail']) && self::$mail = $config['notifyEmail'];
        isset($config['notifyTel']) && self::$notifyTel = $config['notifyTel'];
        isset($config['debug']) && self::$debugMode = $config['debug'];
    }

    /**
     * run 
     * 
     * 
     * @return void
     */
    public function run()
    {
        self::query();
    }

    /**
     * 检测车站正确性
     * @param $station
     */
    private function checkStation($station)
    {
        array_key_exists($station, $this->stations) OR $this->msg("没有找到车站 -> " . $station . ",请确认","Error");
    }

    /**
     * 检测车座类型正确性并返回车座对应代码
     * @param $ticket
     * @return array
     */
    private function checkTicket($ticket)
    {
        $res = [];
        $ticketNameArr = explode(',', $ticket);
        foreach ($ticketNameArr as $ticketName) {
            array_key_exists($ticketName, $this->ticketType) OR 
                $this->msg("车座: {$ticketName} 无效,仅限: 商务座, 特等座, 一等座, 二等座, 高级软卧, 软卧, 硬卧, 软座, 硬座, 无座, 其它","Error");
            $res[] = $this->ticketType[$ticketName];
        }
        return $res;
    }

    /**
     * 格式化车站信息
     * @param $stationStr  字符串
     * @return array  [车站名称 => 车站代码]
     */
    private function formatStationInfo($stationStr)
    {
        $stationInfo = [];
        $stations = explode('|', $stationStr);
        for ($i = 0; $i < count($stations); $i += 5){
            if (isset($stations[$i + 1]) && isset($stations[$i + 2]))
                $stationInfo[$stations[$i + 1]] = $stations[$i + 2];
        }
        return $stationInfo;
    }

    /**
     * getUrl 
     * 
     * 
     * @return void
     */
    private function getUrl()
    {
        $params = [
            'leftTicketDTO.train_date' => $this->query_date,
            'leftTicketDTO.from_station' => $this->from_station_code,
            'leftTicketDTO.to_station' => $this->to_station_code,
            'purpose_codes' => 'ADULT',
        ];
        $this->url = $this->conf['queryApi'] . http_build_query($params);
    }

    /**
     * getHeader 
     * 
     * 
     * @return void
     */
    private function getHeader()
    {
        $UA = [
            'Mozilla/5.0 (Windows; U; Windows NT 5.1; zh-CN; rv:1.9) Gecko/2008052906 Firefox/3.0', 
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_11_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/55.0.2883.95 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.11; rv:50.0) Gecko/20100101 Firefox/50.0',
        ];
        $ip = $this->randIp();
        $cookie = "JSESSIONID=1A006D929A774738884D195229FAB093; route=6f50b51faa11b987e576cdb301e545c4; BIGipServerotn=82838026.50210.0000; RAIL_EXPIRATION=1506360608257; RAIL_DEVICEID=MSesE6JO9hzrLV2IHK115nbCLslPHE2jMEDKX87ChZ_9Sq--orCsGVMFwCuoZZCHQMSHtV0Pf38Tm6e4_2kNhSweMW8nXFWU_nyZX-10pZWsZ-a31wHXXw1IPk2Tv47iuc2rgFkCQnd9Ujxr6XHlHkYVBEuxX6N9; _jc_save_fromStation=%u5929%u6D25%2CTJP; _jc_save_toStation=%u5357%u660C%2CNCG; _jc_save_fromDate=2017-10-01; _jc_save_toDate=2017-09-22; _jc_save_wfdc_flag=dc";

        $header = [
            'Accept' => "*/*",
            'Accept-Encoding' => "gzip,deflate,br",
            'Accept-Language' => 'zh-CN,zh;q=0.8,en;q=0.6',
            'Cache-Control' => "no-cache",
            'Connection' => "keep-alive",
            'Cookie' => $cookie,
            'Host' => 'kyfw.12306.cn',
            'If-Modified-Since' => 0,
            'Pragma' => 'no-cache',
            'Referer' => "",
            'User-Agent' => $UA[array_rand($UA,1)],
            'X-Requested-With' => 'XMLHttpRequest',
            'CLIENT-IP' => $ip,
            'X_FORWARDED-FOR' => $ip
        ];
        return $header;
    }

    /**
     * CURL查询余票信息
     */
    private function query()
    {
        $this->getUrl();

        $client = new \GuzzleHttp\Client([
            'timeout' => 10
        ]);
        $succeed = $failed = 0;
        while (1) {
            $header = $this->getHeader();
            try{
                $result = $client->request('GET',$this->url,[
                    'verify'=>FALSE,
                    'curl' => [
                        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
                    ],
                    'headers' => $header
                ]);
            }
            catch(Exception $e)
            {
                $this->msg($e->getMessage());
                $failed++;
                sleep(5);
                continue;
            }
            $result =  (array)json_decode($result->getBody());
            if( empty($result) )
            {
                sleep(5);
                continue; 
            }

            if ($result['messages']) 
            {
                $this->msg($result['messages']);
            } 
            else if ($result['status']) 
            {
                if( ! isset($result['data']) )  
                {
                    $this->msgAppend(print_r($result,TRUE),'Error');
                    sleep(5); continue;
                }
                if ( $result['data'] )
                {
                    $this->analyzeData($result['data']);
                } else {
                    $this->msg('未查询到有效车次');
                }
            } else {
                $this->msg('请求未响应,正在重试 ￣へ￣');
            }
            sleep(5);
        }
    }

    /**
     * 此函数提供了国内的IP地址
     */
    private function randIP(){
        $ip_long = array(
            array('607649792', '608174079'), //36.56.0.0-36.63.255.255
            array('1038614528', '1039007743'), //61.232.0.0-61.237.255.255
            array('1783627776', '1784676351'), //106.80.0.0-106.95.255.255
            array('2035023872', '2035154943'), //121.76.0.0-121.77.255.255
            array('2078801920', '2079064063'), //123.232.0.0-123.235.255.255
            array('-1950089216', '-1948778497'), //139.196.0.0-139.215.255.255
            array('-1425539072', '-1425014785'), //171.8.0.0-171.15.255.255
            array('-1236271104', '-1235419137'), //182.80.0.0-182.92.255.255
            array('-770113536', '-768606209'), //210.25.0.0-210.47.255.255
            array('-569376768', '-564133889'), //222.16.0.0-222.95.255.255
        );
        $rand_key = mt_rand(0, 9);
        $ip= long2ip(mt_rand($ip_long[$rand_key][0], $ip_long[$rand_key][1]));
        return $ip;
    }

    /**
     * 分析余票并输出信息
     * @param array $trains
     */
    private function analyzeData($trains)
    {
        $msg = '查询次数: ' . $this->query_num++ . "\t" . $this->query_date . "\t" . $this->from_station_name . '(' . $this->from_station_code . ')' . ' ==> ' . $this->to_station_name . '(' . $this->to_station_code . ")\n";
        $trains = $trains->result;
        $notifyMsg = $ticketInfo = "";
        $notifyMsg .= $this->query_date.' '.$this->from_station_name.'到'.$this->to_station_name.":\n";
        foreach ($trains as $key => $item)
        {
            if( preg_match("/系统维护时间/",$item) )
            {
                $data = $item;
                $this->isDay = FALSE;
            }
            else {
                $this->isDay = TRUE;
                $data = strstr($item,'预订');
            }
            $data = explode('|',$data);
            if( in_array($data[2],$this->trainList) &&
                $this->checkSeatType($data,$ticketInfo)
            )
            {
                $notifyMsg .= $ticketInfo;
                $notifyMsg .= "\n";
            }
        }
        $this->msg($msg);
        empty($ticketInfo) OR $this->notify($notifyMsg);
    }

    /**
     * checkSeatType 
     * 
     * @param mixed $data data 
     * 
     * @return void
     */
    public function checkSeatType($data,&$ticketInfo)
    {
        $flag = FALSE;
        foreach( $this->ticketList as $type )
        {
            if( $data[$type] == '有' ||
                is_numeric($data[$type])
            )
            {
                $ticketInfo .= "车次".$data[2].'有'.array_search($type,$this->ticketType); 
                $data[$type] == '有' ? $ticketInfo .= '好多张' : $ticketInfo .= $data[$type].'张'."\n";
                $flag = TRUE;
            }
        }
        if( $flag )
        {
            return TRUE;
        }
        return FALSE;
    }


    /**
     * notify 
     * 
     * @param mixed $msg msg 
     * 
     * @return void
     */
    public function notify($msg)
    {
        if (self::$mail){
            $mail = new Email($this->conf['mailConf']);
            foreach( self::$mail as $mailAddr)
            {
                $mail->send($mailAddr,'有余票,快去12306购买',$msg . " <a href='https://kyfw.12306.cn/otn/lcxxcx/init'>购票</a>");
            }
        }

        if( self::$notifyTel && ! $this->haveNotifyed )
        {
            foreach( self::$notifyTel as $tel )
            {
                $this->sendMsg($tel,$msg);
            }
            $this->haveNotifyed = TRUE;
        }
    }

    /**
     * sendMsg 
     * 
     * @param mixed $tel tel 
     * @param mixed $msg msg 
     * @access public
     * 
     * @return void
     */
    function sendMsg($tel,$msg)
    {
        $client = new \GuzzleHttp\Client([
            'timeout' => 10
        ]);
        $client->request('POST','http://127.0.0.1/sendmsg',[
            'form_params' => [
                "tel" => $tel,
                "msg" => $msg,
            ],
            'curl' => [
                CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4
            ]
        ]);
    }

    /**
     * 输出提示信息
     * @param $msg
     */
    private function msg($msg,$type = "Msg")
    {
        if( self::$debugMode )
        {
            echo $type.":".$msg."\n";
        }
        else {
            file_put_contents($this->logFile,date('Y-m-d H:i:s')."--".$type.":".$msg."\n");
        }
        $type == 'Error' && exit($msg);
    }

    /**
     * msgAppend 
     * 
     * @param mixed $msg msg 
     * @param string $type type 
     * 
     * @return void
     */
    private function msgAppend($msg,$type = "Msg")
    {
        if( self::$debugMode )
        {
            echo $type.":".$msg."\n";
        }
        else {
            file_put_contents($this->logFile,date('Y-m-d H:i:s')."--".$type.":".$msg."\n",FILE_APPEND);
        }
        $type == 'Error' && exit($msg);
    }
}

