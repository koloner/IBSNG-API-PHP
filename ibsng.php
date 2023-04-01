<?php

/**
 * Example Connect
 * $login = [
 * 'username' => 'admin',
 * 'password' => '123456',
 *		'hostname' => 'ibs.netxping.com',
 *	];
 *	$ibsng =  new IBSng($login);
 */
class IBSng
{
    static public $_instance;

    protected $hostname, $username, $password, $port, $timeout;
    protected $isConnected = false;
    protected $loginData = [];
    protected $autoConnect = true;
    protected $cookiePathName = null;
    protected $handler = null;
    protected $agent = 'phpIBSng web Api';

    /**
     * @param boolean $autoConnect
     */
    public function setAutoConnect($autoConnect)
    {
        $this->autoConnect = $autoConnect;
    }


    public function __construct(Array $loginArray)
    {
        /*
         * Curl library existence
         */
        if (!extension_loaded('curl')) {
            throw new \Exception ("You need to load/activate the curl extension.");
        }

        /*
         * Hide LibXML parse errors
         */
        libxml_use_internal_errors(true);


        self::$_instance = $this;

        $this->loginData = $loginArray;
        if (!$this->loginData['username'] ||
            !$this->loginData['password'] ||
            !$this->loginData['hostname']
        ) {

            throw new Exception('IBSng needs correct login information');
        }

        $this->hostname = $loginArray['hostname'];
        $this->username = $loginArray['username'];
        $this->password = $loginArray['password'];
        $this->port = $loginArray['port'] ?? 80;
        $this->timeout = $loginArray['timeout'] ?? 30;

        $this->cookiePathName = sys_get_temp_dir() . '/.' . self::class;

        /**
         * If Auto Connect True
         */
        if ($this->autoConnect) {
            $this->connect();
        }

    }
    /**
     * Check Url And  Port
     */
    protected function hostNameHealth($hostname = false, $port = false)
    {
        if ($hostname == false) {
            $hostname = $this->hostname;
        }
        if ($port == false) {
            $port = $this->port;
        }
        $fp = @fsockopen($hostname, $port);
        return $fp;
    }

    /**
     * Get Cookie
     */
    protected function getCookie()
    {
        return $this->cookiePathName;
    }

    /**
     * Connect
     */
    public function connect()
    {
        if ($this->isConnected()) {
            return ['result'=>true];
        }

        /*
        * Login
        */
        if(!$this->login()){
            return ['result'=>false,'error'=>"Can't login to IBSng. Wrong username or password"];
        }
        /*
         * set connection as valid
         */
        return $this->isConnected = true;
    }

    /**
     * Disconnect
     */
    public function disconnect()
    {
        if ($this->handler) {
            @unlink($this->getCookie());
            @curl_close($this->handler);
        }
    }

    /**
     * Check Connected
     */
    public function isConnected()
    {
        return $this->isConnected;
    }

    /**
     *  Add User
     * @param string $username
     * @param string $password
     * @param string $group
     * @param int $credit
     */
    public function addUser(string $username = null,string $password = null,string $group = null,int $credit = null)
    {
        return $this->_addUser($group, $username, $password, $credit);
    }

    /**
     * Delete User
     * if $username $uid false
     * if $username is $uid true
     * @param string $username
     * @param boolean $uid
     */
    public function deleteUser($username,$uid=false)
    {
        return $this->_delUser($username,$uid);
    }

    /**
     * Get Info User
     * @param string $username
     * @param boolean $withpassword
     * @param boolean $uid
     */
    public function getUser($username,$withpassword = true,$uid=false)
    {
        return $this->infoByUsername($username, $withpassword,$uid);
    }

    /**
     * Update User relative expire date
     * @param string $username
     * @param int $c
     * @param string $type Days , Months , Years
     */
    public function up_date_User(string $username,int $c,string $type='Days')
    {
        return $this->_up_date_User($username,$c, $type);
    }

    /**
     * Update User Charge 
     * @param string $username
     * @param string $Charge
     */
    
    public function up_Charge_User(string $username,string $Charge)
    {
        return $this->_up_Charge_User($username,$Charge);
    }
    /**
     * Clean Reports
     * @param string $log connection_logs , credit_changes , user_audit_logs , snapshots , web_analyzer
     * @param int $time Hours , Days , Months , Years
     * @param string $time_value
     */
    public function clean_report(string $log,int $time,string $time_value = 'Days'){
        $log2 = str_replace(['connection_logs' , 'credit_changes' , 'user_audit_logs' , 'snapshots' , 'web_analyzer'],['connection_log' , 'credit_change' , 'user_audit_log' , 'snapshots' , 'web_analyzer'],$log);
        return $this->_clean_report($log,$log2,$time,$time_value);
    }
    protected function login()
    {
        $action = 'IBSng/admin/';
        $postData['username'] = $this->username;
        $postData['password'] = $this->password;
        $output = $this->request($action, $postData, true);
        if (strpos($output, 'admin_index') > 0) {
            return ['result'=>true];
        }else{
            return ['result'=>false];
        }
    }

    protected function _up_date_User($username, $c, $type)
    {
        $action = 'IBSng/admin/plugins/edit.php';
        $user = $this->infoByUsername($username)['info'];
        
        preg_match('/([0-9])\w+/',$user['relative_expire_date_gp'],$m);
        if(strpos($user['relative_expire_date_gp'],'Days') !== false){
            $d = $m[0] * 1;
        }elseif(strpos($user['relative_expire_date_gp'],'Months') !== false){
            $d = $m[0] * 31;
        }elseif(strpos($user['relative_expire_date_gp'],'Years') !== false){
            $d = $m[0] * 365;
        }
        
        if($user['first_login'] == '0'){
        $new = $c + $d;
        }else{
            $exp= $user['expire_date'];
            if(strtotime($exp) <= time()){
                $date1 = new DateTime($exp);
                $date2 = date('Y-m-d H:i:s');
                $date2 = new DateTime($date2);
                $interval = $date1->diff($date2);
                $new = $interval + $c + $d;
            }else{
                $new = $c + $d;
            }
        }
        $post_data['target'] = 'user';
        $post_data['target_id'] = $user['uid'];
        $post_data['update'] = 1;
        $post_data['edit_tpl_cs'] = 'rel_exp_date';
        $post_data['tab1_selected'] = 'Exp_Dates';
        $post_data['attr_update_method_0'] = 'relExpDate';
        $post_data['has_rel_exp'] = 't';
        $post_data['rel_exp_date'] = $new;
        $post_data['rel_exp_date_unit'] = $type;
        $output = $this->request($action, $post_data, true);
        return ['result'=>true];
    }

    protected function _up_Charge_User($username, $Charge)
    {
        $action = 'IBSng/admin/plugins/edit.php';
        $user = $this->infoByUsername($username)['info'];
        
        $post_data['target'] = 'user';
        $post_data['target_id'] = $user['uid'];
        $post_data['update'] = 1;
        $post_data['edit_tpl_cs'] = 'normal_charge';
        $post_data['attr_update_method_0'] = 'normalCharge';
        $post_data['has_normal_charge'] = 't';
        $post_data['normal_charge'] = $Charge;
        $output = $this->request($action, $post_data, true);
        return ['result'=>true];
    }

    protected function _clean_report($log,$log2,$time, $time_value)
    {
        $action = 'IBSng/admin/report/clean_reports.php';
        
        $post_data['delete_'.$log] = 'user';
        $post_data[$log.'_date'] = $time;
        $post_data[$log.'_unit'] = $time_value;
        $output = $this->request($action, $post_data, false);
        return ['result'=>true];
    }

    protected function _addUser($group_name, $username, $password, $credit)
    {
        $owner = $this->username;
        $IBSng_uid = $this->cr8_uid($group_name, $credit);
        $action = 'IBSng/admin/plugins/edit.php?edit_user=1&user_id=' . $IBSng_uid . '&submit_form=1&add=1&count=1&credit=1&owner_name=' . $owner . '&group_name=' . $group_name . '&x=35&y=1&edit__normal_username=normal_username';
        $post_data['target'] = 'user';
        $post_data['target_id'] = $IBSng_uid;
        $post_data['update'] = 1;
        $post_data['edit_tpl_cs'] = 'normal_username';
        $post_data['attr_update_method_0'] = 'normalAttrs';
        $post_data['has_normal_username'] = 't';
        $post_data['current_normal_username'] = '';
        $post_data['normal_username'] = $username; // username
        $post_data['password'] = $password; //password
        $post_data['normal_save_user_add'] = 't';
        $post_data['credit'] = $credit;
        $output = $this->request($action, $post_data, true);
        if (strpos($output, 'exist')) {
            $this->_delUser($IBSng_uid,true);
            return ['result'=>false,'error'=>"username already exists"];
        }
        if (strpos($output, 'IBSng/admin/user/user_info.php?user_id_multi')) {
            return ['result'=>true];
        }
    }

    protected function infoByUsername($username, $withPassword = false,$uid=false, $output = null)
    {
        if($uid){
            $action = 'IBSng/admin/user/user_info.php?user_id_multi=' . $uid;
            $output = $this->request($action);
        }else{
        if ($output == null) {
            $action = 'IBSng/admin/user/user_info.php?normal_username_multi=' . $username;
            $output = $this->request($action);
        }
        }
        if (strpos($output, 'does not exists') !== false) {
            return ['result'=>false,'error'=>'['.$username.'] Not Found'];
        }else{

        $dom = new \DomDocument();
        $dom->loadHTML($output);
        $finder = new \DomXPath($dom);


        $classname = 'Form_Content_Row_Right_textarea_td_light';
        $nodes = $finder->query("//*[contains(@class, '$classname')]");
        $lock = trim($nodes->item(0)->nodeValue);
        if (strpos($lock, 'Yes') === false) {
            $locked = '0';
        } else {
            $locked = '1';
        }

        $classname = 'Form_Content_Row_Right_userinfo_light';
        $nodes = $finder->query("//*[contains(@class, '$classname')]");
        if($uid){
        $username = $nodes->item(0)->nodeValue;
        $username = trim($username);
        if ($username == '---------------') {
            $username = '0';
        }
        }
        $multi = trim($nodes->item(4)->nodeValue);
        if (strpos($multi, 'instances') === false) {
            $multi = 0;
        } else {
            $multi = trim(str_replace('instances', '', $multi));
        }

        $group_pattern = '<a href="/IBSng/admin/group/group_info.php?group_name=';
        $group_pos1 = strpos($output, $group_pattern);
        $group_trim1 = substr($output, $group_pos1 + strlen($group_pattern), 100000);
        $group_pos2 = strpos($group_trim1, '"');
        $group_name = substr($group_trim1, 0, $group_pos2); // final for group name
        if (substr($group_name, 0, 6) == 'Server') {
            throw new \Exception ("failed to retrieve group name");
        }
        $uid_pattern = 'User ID';
        $uid_pos1 = strpos($output, $uid_pattern);
        $uid_trim1 = substr($output, $uid_pos1, 100000);
        $uid_pattern2 = '<td class="Form_Content_Row_Right_light">';
        $uid_pos2 = strpos($uid_trim1, $uid_pattern2);
        $uid_trim2 = substr($uid_trim1, $uid_pos2 + strlen($uid_pattern2), 100);
        $uid_pattern3 = '</td>';
        $uid_pos3 = strpos($uid_trim2, $uid_pattern3);
        $uid_trim3 = substr($uid_trim2, 0, $uid_pos3);
        $uid = trim($uid_trim3);

        $owner_pattern = 'Owner Admin';
        $owner_pos1 = strpos($output, $owner_pattern);
        $owner_trim1 = substr($output, $owner_pos1, 100000);
        $owner_pattern2 = '<td class="Form_Content_Row_Right_dark">';
        $owner_pos2 = strpos($owner_trim1, $owner_pattern2);
        $owner_trim2 = substr($owner_trim1, $owner_pos2 + strlen($owner_pattern2), 100);
        $owner_pattern3 = '</td>';
        $owner_pos3 = strpos($owner_trim2, $owner_pattern3);
        $owner_trim3 = substr($owner_trim2, 0, $owner_pos3);
        $owner = trim($owner_trim3);

        $comment_pattern = ' Comment
     :';
        $comment_pos1 = strpos($output, $comment_pattern);
        $comment_trim1 = substr($output, $comment_pos1, 100000);
        $comment_pattern2 = '<td class="Form_Content_Row_Right_textarea_td_dark">';
        $comment_pos2 = strpos($comment_trim1, $comment_pattern2);
        $comment_trim2 = substr($comment_trim1, $comment_pos2 + strlen($comment_pattern2), 100);
        $comment_pattern3 = '</td>';
        $comment_pos3 = strpos($comment_trim2, $comment_pattern3);
        $comment_trim3 = substr($comment_trim2, 0, $comment_pos3);
        $comment = trim($comment_trim3);
        if ($comment == '---------------') {
            $comment = '0';
        }

        $name_pattern = ' Name
     :';
        $name_pos1 = strpos($output, $name_pattern);
        $name_trim1 = substr($output, $name_pos1, 100000);
        $name_pattern2 = '<td class="Form_Content_Row_Right_textarea_td_light">';
        $name_pos2 = strpos($name_trim1, $name_pattern2);
        $name_trim2 = substr($name_trim1, $name_pos2 + strlen($name_pattern2), 100);
        $name_pattern3 = '</td>';
        $name_pos3 = strpos($name_trim2, $name_pattern3);
        $name_trim3 = substr($name_trim2, 0, $name_pos3);
        $name = trim($name_trim3);
        if ($name == '---------------') {
            $name = '0';
        }

        $phone_pattern = ' Phone
     :';
        $phone_pos1 = strpos($output, $phone_pattern);
        $phone_trim1 = substr($output, $phone_pos1, 100000);
        $phone_pattern2 = '<td class="Form_Content_Row_Right_textarea_td_dark">';
        $phone_pos2 = strpos($phone_trim1, $phone_pattern2);
        $phone_trim2 = substr($phone_trim1, $phone_pos2 + strlen($phone_pattern2), 100);
        $phone_pattern3 = '</td>';
        $phone_pos3 = strpos($phone_trim2, $phone_pattern3);
        $phone_trim3 = substr($phone_trim2, 0, $phone_pos3);
        $phone = trim($phone_trim3);
        if ($phone == '---------------') {
            $phone = '0';
        }

        $creation_pattern = 'Creation Date';
        $creation_pos1 = strpos($output, $creation_pattern);
        $creation_trim1 = substr($output, $creation_pos1, 100000);
        $creation_pattern2 = '<td class="Form_Content_Row_Right_light">';
        $creation_pos2 = strpos($creation_trim1, $creation_pattern2);
        $creation_trim2 = substr($creation_trim1, $creation_pos2 + strlen($creation_pattern2), 100);
        $creation_pattern3 = '</td>';
        $creation_pos3 = strpos($creation_trim2, $creation_pattern3);
        $creation_trim3 = substr($creation_trim2, 0, $creation_pos3);
        $creation_date = trim($creation_trim3);

        $status_pattern = 'Status';
        $status_pos1 = strpos($output, $status_pattern);
        $status_trim1 = substr($output, $status_pos1, 100000);
        $status_pattern2 = '<td class="Form_Content_Row_Right_dark">';
        $status_pos2 = strpos($status_trim1, $status_pattern2);
        $status_trim2 = substr($status_trim1, $status_pos2 + strlen($status_pattern2), 100);
        $status_pattern3 = '</td>';
        $status_pos3 = strpos($status_trim2, $status_pattern3);
        $status_trim3 = substr($status_trim2, 0, $status_pos3);
        $status = trim($status_trim3);

        $exp_pattern = 'Nearest Expiration Date:';
        $exp_pos1 = strpos($output, $exp_pattern);
        $exp_trim1 = substr($output, $exp_pos1, 10000);
        $exp_pattern2 = '<td class="Form_Content_Row_Right_userinfo_light">';
        $exp_pos2 = strpos($exp_trim1, $exp_pattern2);
        $exp_trim2 = substr($exp_trim1, $exp_pos2 + strlen($exp_pattern2), 1000);
        $exp_pattern3 = '</td>';
        $exp_pos3 = strpos($exp_trim2, $exp_pattern3);
        $exp_trim3 = substr($exp_trim2, 0, $exp_pos3);
        $exp = trim($exp_trim3);
        if ($exp == '---------------') {
            $exp = '0';
        }

        $absExp_pattern = 'Nearest Expiration Date:';
        $absExp_pos1 = strpos($output, $absExp_pattern);
        $absExp_trim1 = substr($output, $absExp_pos1, 10000);
        $absExp_pattern2 = '<td class="Form_Content_Row_Right_userinfo_light">';
        $absExp_pos2 = strpos($absExp_trim1, $absExp_pattern2);
        $absExp_trim2 = substr($absExp_trim1, $absExp_pos2 + strlen($absExp_pattern2), 1000);
        $absExp_pattern3 = '</td>';
        $absExp_pos3 = strpos($absExp_trim2, $absExp_pattern3);
        $absExp_trim3 = substr($absExp_trim2, 0, $absExp_pos3);
        $absExp = trim($absExp_trim3);
        if ($absExp == '---------------') {
            $absExp = '0';
        }

        $relExp_pattern = 'Relative Expiration Date:';
        $relExp_pos1 = strpos($output, $relExp_pattern);
        $relExp_trim1 = substr($output, $relExp_pos1, 10000);
        $relExp_pattern2 = '<td class="Form_Content_Row_Right_userinfo_dark">';
        $relExp_pos2 = strpos($relExp_trim1, $relExp_pattern2);
        $relExp_trim2 = substr($relExp_trim1, $relExp_pos2 + strlen($relExp_pattern2), 1000);
        $relExp_pattern3 = '</td>';
        $relExp_pos3 = strpos($relExp_trim2, $relExp_pattern3);
        $relExp_trim3 = substr($relExp_trim2, 0, $relExp_pos3);
        $relExp = trim($relExp_trim3);
        if ($relExp == '---------------') {
            $relExp = '0';
        }

        $relExpgp_pattern = '<div id="tab1_Exp_Dates_content" align=center>';
        $relExpgp_pos1 = strpos($output, $relExpgp_pattern);
        $relExpgp_trim1 = substr($output, $relExpgp_pos1, 5000);
        $relExpgp_pattern2 = '<td class="Form_Content_Row_groupinfo_dark" align=center>';
        $relExpgp_pos2 = strpos($relExpgp_trim1, $relExpgp_pattern2);
        $relExpgp_trim2 = substr($relExpgp_trim1, $relExpgp_pos2 + strlen($relExpgp_pattern2), 1000);
        $relExpgp_pattern3 = '</td>';
        $relExpgp_pos3 = strpos($relExpgp_trim2, $relExpgp_pattern3);
        $relExpgp_trim3 = substr($relExpgp_trim2, 0, $relExpgp_pos3);
        $relExpgp = trim($relExpgp_trim3);

        $first_login_pattern = 'First Login:';
        $first_login_pos1 = strpos($output, $first_login_pattern);
        $first_login_trim1 = substr($output, $first_login_pos1, 10000);
        $first_login_pattern2 = '<td class="Form_Content_Row_Right_userinfo_dark">';
        $first_login_pos2 = strpos($first_login_trim1, $first_login_pattern2);
        $first_login_trim2 = substr($first_login_trim1, $first_login_pos2 + strlen($first_login_pattern2), 1000);
        $first_login_pattern3 = '</td>';
        $first_login_pos3 = strpos($first_login_trim2, $first_login_pattern3);
        $first_login_trim3 = substr($first_login_trim2, 0, $first_login_pos3);
        $first_login = trim($first_login_trim3);
        if ($first_login == '---------------') {
            $first_login = '0';
        }

        $credit_pattern = '<td class="Form_Content_Row_Left_dark"> 	Credit';
        $credit_pos1 = strpos($output, $credit_pattern);
        $credit_trim1 = substr($output, $credit_pos1, 10000);
        $credit_pattern2 = '<td class="Form_Content_Row_Right_dark">';
        $credit_pos2 = strpos($credit_trim1, $credit_pattern2);
        $credit_trim2 = substr($credit_trim1, $credit_pos2 + strlen($credit_pattern2), 1000);
        $credit_pattern3 = '<a class';
        $credit_pos3 = strpos($credit_trim2, $credit_pattern3);
        $credit_trim3 = substr($credit_trim2, 0, $credit_pos3);
        $credit = trim($credit_trim3);
        $credit = str_replace(',', '', $credit);

        
        $info['username'] = $username;
        $info['name'] = $name;
        $info['comment'] = $comment;
        $info['phone'] = $phone;
        $info['owner'] = $owner;
        $info['credit'] = $credit;
        $info['uid'] = $uid;
        $info['group'] = $group_name;
        $info['first_login'] = $first_login;
        $info['creation_date'] = $creation_date;
        $info['nearest_expire_date'] = $exp;
        $info['expire_date'] = $exp;
        $info['absolute_expire_date'] = $absExp;
        $info['relative_expire_date'] = $relExp;
        $info['relative_expire_date_gp'] = $relExpgp;
        $info['status'] = $status;
        $info['locked'] = $locked;
        $info['multi'] = $multi;
        if ($withPassword) {
            $info['password'] = $this->getPassword($username, $uid);
        }
        return ['result'=>true,'info'=>$info];
    }
    }

    protected function request($action, $postData = array(), $header = FALSE)
    {
        if (empty($action)) {
            throw new \Exception('Url specified in curl request is empty ');
        }
        $url = 'http://'.$this->hostname.'/'.$action;
        $this->handler = curl_init();
        curl_setopt($this->handler, CURLOPT_CONNECTTIMEOUT, 0);
        curl_setopt($this->handler, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($this->handler, CURLOPT_URL, $url);
        curl_setopt($this->handler, CURLOPT_PORT, $this->port);
        curl_setopt($this->handler, CURLOPT_POST, true);
        curl_setopt($this->handler, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($this->handler, CURLOPT_HEADER, $header);
        curl_setopt($this->handler, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($this->handler, CURLOPT_FOLLOWLOCATION, TRUE);
        curl_setopt($this->handler, CURLOPT_USERAGENT, $this->agent);
        curl_setopt($this->handler, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($this->handler, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->handler, CURLOPT_COOKIEFILE, $this->getCookie());
        curl_setopt($this->handler, CURLOPT_COOKIEJAR, $this->getCookie());
        $output = curl_exec($this->handler);
        if (curl_errno($this->handler) != 0) {
            throw new \Exception(curl_error($this->handler) . $url."\n");
        }
        curl_close($this->handler);
        return $output;
    }

    protected function getPassword($username, $uid = null)
    {
        if ($uid == null) {
            $uid = $this->isUsername($username);
        }

        $action = 'IBSng/admin/plugins/edit.php';
        $postData['user_id'] = $uid;
        $postData['edit_user'] = 1;
        $postData['attr_edit_checkbox_2'] = 'normal_username';

        $output = $this->request($action, $postData);

        $phrase = '<td class="Form_Content_Row_Right_light">	<input type=text id="password" name="password" value="';
        $pos1 = strpos($output, $phrase);
        $leftover = str_replace($phrase, '', substr($output, $pos1, strlen($phrase) + 1000));
        $password = substr($leftover, 0, strpos($leftover, '"'));
        if (isset($password)) {
            return trim($password);
        } else {
            return ['result'=>false];
        }
    }
    private function isUsername($username)
    {
        $action = 'IBSng/admin/user/user_info.php?normal_username_multi=' . $username;
        $output = $this->request($action);

        if (strpos($output, 'does not exists') == true) {
            return false;
        } else {
            $pattern1 = 'change_credit.php?user_id=';
            $pos1 = strpos($output, $pattern1);
            $sub1 = substr($output, $pos1 + strlen($pattern1), 100);
            $pattern2 = '"';
            $pos2 = strpos($sub1, $pattern2);
            $sub2 = substr($sub1, 0, $pos2);
            return $sub2;
        }
    }

    private function cr8_uid($group_name, $credit)
    {
        $action = 'IBSng/admin/user/add_new_users.php';
        $post_data['submit_form'] = 1;
        $post_data['add'] = 1;
        $post_data['count'] = 1;
        $post_data['credit'] = $credit;
        $post_data['owner_name'] = $this->username;
        $post_data['group_name'] = $group_name;
        $post_data['edit__normal_username'] = 1;
        $output = $this->request($action, $post_data, true);
        $pattern1 = 'user_id=';
        $pos1 = strpos($output, $pattern1);
        $sub1 = substr($output, $pos1 + strlen($pattern1), 100);
        $pattern2 = '&su';
        $pos2 = strpos($sub1, $pattern2);
        $sub2 = substr($sub1, 0, $pos2);
        return $sub2;
    }

    protected function _delUser($username, $uidtrue=false,$logs = true, $audit = true)
    {
        if($uidtrue){
        $uid = $username;
        }else{
        $uid = $this->_userExists($username);
        }
        if ($uid == false){
            return ['result'=>false,'error'=>"user does not exists"];
        }else{
        $action = 'IBSng/admin/user/del_user.php';
        $post_data['user_id'] = $uid;
        $post_data['delete'] = 1;
        $post_data['delete_comment'] = '';
        if ($logs)
            $post_data['delete_connection_logs'] = 'on';
        if ($audit)
            $post_data['delete_audit_logs'] = 'on';
        $output = $this->request($action, $post_data, true);
        if (strpos($output, 'Successfully')) {
            return ['result'=>true];
        } else {
            return ['result'=>false];
        }
    }
    }

    public function _userExists($username)
    {
        $action = 'IBSng/admin/user/user_info.php?normal_username_multi=' . $username;
        $output = $this->request($action, array(), true);
        if (strpos($output, 'does not exists') == true) {
            return ['result'=>false,'error'=>'does not exists'];
        } else {
            $pattern1 = 'change_credit.php?user_id=';
            $pos1 = strpos($output, $pattern1);
            $sub1 = substr($output, $pos1 + strlen($pattern1), 100);
            $pattern2 = '"';
            $pos2 = strpos($sub1, $pattern2);
            $sub2 = substr($sub1, 0, $pos2);
            return $sub2;
        }
    }
}
