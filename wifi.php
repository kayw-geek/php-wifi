<?php
class Wifi
{
    const WINDOWS = 'WINDOWS';
    const MAC = 'MAC';
    const LINUX = 'LINUX';
    const XMLName = 'WLAN-weikai.xml';
    const COMMENT_NAME = [
        'scan',
        'connect',
        'disconnect',
        'info',
        'password'
    ];
    const WINDOWS_COMMENT = [
        'scan'=>'netsh wlan show networks mode=bssid',
        'info'=>'netsh wlan show profile name=%s key=clear',
        'disconnect'=>'netsh wlan disconnect',
        'addconfig'=>'netsh wlan add profile filename='.self::XMLName,
        'connect'=>"netsh wlan connect name=s%",
    ];
    const LINUX_COMMENT = [
        'scan'=>'netsh wlan show networks mode=bssid',
        'info'=>'ifconfig -a',
        'disconnect'=>'netsh wlan disconnect',
        'addconfig'=>'wpa_passphrase s% s% > /etc/wpa_supplicant/s%.conf',
        'connect'=>"wpa_supplicant -i wlan0 -c /etc/wpa_supplicant/s%.conf -B",
    ];
    private $params = [];
    private $paramsCount = 0;
    private $commentKeyVal = [];
    private $currentSystem;
    private $constNameComment;
    private $r;

    public function __construct()
    {
        $this->r = new ReflectionClass(__CLASS__);
        $this->getOS();
        $this->getParams();
        $this->getParamsCount();
        $this->checkComment();
        $this->handle();
    }

    private function getOS()
    {
        $this->currentSystem = substr(PHP_OS,0,3) === 'WIN' || substr(PHP_OS,0,3) === 'Win' ? self::WINDOWS : null;
        if ($this->currentSystem === null){
            $this->currentSystem = PHP_OS === 'Unix' ? self::MAC : self::LINUX;
        }
        $this->constNameComment = $this->currentSystem.'_COMMENT';
    }
    private function checkComment()
    {
        if ($this->paramsCount === 0){
            exit();
        }
        foreach ($this->params as $key => $param){
            $subParam = substr($param,2);
            if (in_array($subParam,self::COMMENT_NAME)){
                $this->commentKeyVal[$subParam] = empty($this->params[$key+1]) ? null : $this->params[$key+1];
            }
        }
    }

    public function handle()
    {
        if (!is_array($this->commentKeyVal) || empty($this->commentKeyVal)){
            exit();
        }
        foreach ($this->commentKeyVal as $key=>$val){
            if ($val === null){
                system($this->r->getConstant($this->constNameComment)[$key]);
                break;
            }else{
                if ($key == 'connect'){
                    call_user_func([__CLASS__,$key],$val,$this->commentKeyVal['password'] ?? '');
                    break;
                }
                call_user_func([__CLASS__,$key],$val);
                break;
            }
        }
    }


    public function info($ssid)
    {
        system(sprintf($this->r->getConstant($this->constNameComment)[__FUNCTION__],$ssid));
    }
    public function connect($ssid,$password)
    {
        if (empty($password)){
            system(sprintf($this->r->getConstant($this->constNameComment)[__FUNCTION__],$ssid));
        }
        $this->createXML($ssid,$password);
        system($this->r->getConstant($this->constNameComment)['addconfig']);
        system(sprintf($this->r->getConstant($this->constNameComment)[__FUNCTION__],$ssid));
        unlink('./'.self::XMLName);
    }
    public function getCommentKeyVal()
    {
        return $this->commentKeyVal;
    }
    private function createXML($ssid,$password)
    {
        $ssidhex = bin2hex($ssid);
        $xml = <<<XML
<?xml version="1.0"?>
<WLANProfile xmlns="http://www.microsoft.com/networking/WLAN/profile/v1">
	<name>$ssid</name>
	<SSIDConfig>
		<SSID>
			<hex>$ssidhex</hex>
			<name>$ssid</name>
		</SSID>
	</SSIDConfig>
	<connectionType>ESS</connectionType>
	<connectionMode>auto</connectionMode>
	<autoSwitch>true</autoSwitch>
	<MSM>
		<security>
			<authEncryption>
				<authentication>WPA2PSK</authentication>
				<encryption>AES</encryption>
				<useOneX>false</useOneX>
			</authEncryption>
			<sharedKey>
				<keyType>passPhrase</keyType>
				<protected>false</protected>
				<keyMaterial>$password</keyMaterial>
			</sharedKey>
		</security>
	</MSM>
</WLANProfile>
XML;
        $xmlfile = fopen(self::XMLName,'w') or die("Unable to open file!");
        fwrite($xmlfile, $xml);
        fclose($xmlfile);
    }

    public function getParams()
    {
        $this->params = $_SERVER['argv'];
    }

    public function getParamsCount()
    {
        $this->paramsCount = $_SERVER['argc'] ?? 0;
    }
}
