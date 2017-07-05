<?php

namespace app\prog\controller;

use think\Controller;
use think\Db;
use think\helper\Hash;
use app\prog\controller\App;


class Publics extends App{

	//注册接口
	public function register() {
		//获取当前用户的注册信息

        $code = trim(input('verify'));
        $username = trim(input('username'));
        $mobile = trim(input('mobile'));
        $password = trim(input('password'));
        $agent = trim(input('agent')) ? trim(input('agent')) : '';  //邀请码


        $memberInfo = db('admin_user')->where("username='$username' or mobile=$mobile")->field('id')->find();
        // $memberInfo = db('admin_user')->where("username='$username' or mobile=$mobile")->field('id')->fetchsql()->find();

        if($memberInfo) {
            $data = array('code'=>3,'msg'=>'用户名或手机号已存在，请重新输入');  
            ajax($data);
        }


        if(!preg_match("/^[a-zA-Z][a-zA-Z0-9_]{5,21}$/",$username)){    
            $data = array('code'=>1,'msg'=>'用户名有误，请重新输入');
            ajax($data);  
        } 

        if(!preg_match("/^1[34578]{1}\d{9}$/",$mobile)){    
            $data = array('code'=>2,'msg'=>'手机号码有误，请重新输入');
            ajax($data);  
        }


        if(!empty($agent)) {
            $memberInfo = db('admin_user')->where("agent=$agent")->field('id')->find();
            if(empty($memberInfo)) {
                $data = array('code'=>4,'msg'=>'邀请码不存在，请重新输入');  
                ajax($data);
            }
        }


        //判断当前用户的短信验证码是否正确
        $where = array('addtime'=>array('gt',time()-600));
        $verify = db('code')->where("mobile=$mobile")->where($where)->field('code')->order('id desc')->limit(1)->find();


        $memberData = array();

        if($code == $verify['code']) {//验证码正确
            
            $memberData['username'] = $username;
            $memberData['mobile'] = $mobile;
            $memberData['password'] = Hash::make((string)$password);
            $memberData['agent'] = $agent;  //邀请码

            $memberData['nickname'] = $mobile;
            $memberData['create_time'] = time();
            $memberData['last_login_time'] = time();
            $memberData['sort'] = 100;
            $memberData['status'] = 1;
            $memberData['role'] = 3;

            if(db('admin_user')->insert($memberData)) {
                $data = array('code'=>0,'msg'=>'注册成功');  
            }else{
                $data = array('code'=>5,'msg'=>'注册失败');  
            }

        }else{//验证码错误
            $data = array('code'=>6,'msg'=>'验证码有误或超时，请重新获取');  
        }
		
        ajax($data);
	}



	//获取短信验证码接口
	public function sendmsg() {
        //获取用户输入的手机号
		$mobile = trim(input('mobile'));

		if(!preg_match("/^1[34578]{1}\d{9}$/",$mobile)){    
	        $data = array('code'=>1,'msg'=>'手机号码有误，请重新输入');
            ajax($data);  
	    }  


        // $data = $this->request->post();
        $flag = 0;
        $params = ''; //要post的数据 
        $verify = rand(123456, 999999); //获取随机验证码
        // $mobile = $data['telphone']; //手机号
        $argv = array(
            'name' => 'ahjppt', //必填参数。用户账号
            'pwd' => '7DAC9C797EE96DD0195F93EE3B90', //必填参数 接口密码
            'content' => '您好,本次验证码为' . $verify . ',十分钟内有效', //必填参数。发送内容（1-500 个汉字）UTF-8编码
            'mobile' => $mobile, //必填参数。手机号码。多个以英文逗号隔开
            'stime' => '', //可选参数。发送时间，填写时已填写的时间发送，不填时为当前时间发送
            'sign' => '人人家智慧社区', //必填参数。用户签名。
            'type' => 'pt', //必填参数。固定值 pt
            'extno' => ''//可选参数，扩展码，用户定义扩展码，只能为数字
        );
        //构造要post的字符串 
        foreach ($argv as $key => $value) {
            if ($flag != 0) {
                $params .= "&";
                $flag = 1;
            }
            $params .= $key . "=";
            $params .= urlencode($value); // urlencode($value); 
            $flag = 1;
        }

        $url = "http://web.cr6868.com/asmx/smsservice.aspx?" . $params; //提交的url地址

        $con = substr(file_get_contents($url), 0, 1);  //获取信息发送后的状态
        

        if ($con == '0') {
            $code['code'] = $verify;
            $code['mobile'] = $mobile;
            $code['addtime'] = time();
            db('code')->insert($code);
            
            $data = array('code'=>0,'msg'=>'验证码发送成功');
        } else {
            $data = array('code'=>99,'msg'=>'验证码发送失败');
        }

        ajax($data);
    }



	//登陆接口
    public function login(){
        //获取用户输入的登陆信息
        $username = trim(input('username'));

        $password = trim(input('password'));

        if(preg_match("/^1[34578]{1}\d{9}$/",$username)) {
            // 手机号登录
            $mobile = $username;

            $memberInfo = db('admin_user')->where("mobile=$mobile")->field('id,token,password,status')->find();     

        }else{//用户名登陆
            $memberInfo = db('admin_user')->where("username='$username'")->field('id,token,password,status')->find();
        }

        if(empty($memberInfo)) {
            $data = array('code'=>1,'msg'=>'用户不存在，请重新输入'); 
            ajax($data);
        }


        if($memberInfo['status'] == 0) {
            $data = array('code'=>2,'msg'=>'该账户已被禁用'); 
            ajax($data);
        }

        if(!Hash::check($password,$memberInfo['password'])) {
            $data = array('code'=>3,'msg'=>'用户名或密码错误'); 
            ajax($data);
        }   

        $token = md5(mt_rand(1,999999).time().$memberInfo['id']);
        //更新用户token字段,确保无重复出现的token
        while(true) {
          $count = db('admin_user')
            ->where("token='$token'")
            ->count('id');

          if($count<=0) {
            break;
          }
          $token = md5(mt_rand(1,999999).time().$memberInfo['id']);
        }


        //更新用户salt,pwd,token等字段
        $memberData['token'] = $token;
        if(!db("admin_user")->where("id={$memberInfo['id']}")->update($memberData)){
            $data = array('code'=>99,'msg'=>'登录失败[token保存失败]'); 
            ajax($data);
        }

        $userInfo = db('admin_user')->field('id,username,mobile,mobile_bind,nickname,agent,avatar,money,score')->where("token='$token'")->find();

        unset($memberData['password']);
        
        $data = array('code'=>0,'msg'=>'登陆成功','data'=>array('token'=>$token,'userInfo'=>$userInfo)); 
        ajax($data);
    }



    //忘记密码
    public function forgetPwd() {
        //获取用户的手机号
        $mobile = trim(input('mobile'));

        $memberInfo = db('admin_user')->field('id')->where("mobile=$mobile")->find();

        if(empty($memberInfo)) {
            $data = array('code'=>3,'msg'=>'用户不存在，请重新输入');  
            ajax($data);
        }

        
        $password = trim(input('password'));
        $repassword = trim(input('repassword'));

        //判断2次输入密码是否一致
        if($password !== $repassword) {
            $data = array('code'=>4,'msg'=>'2次密码必须一致');  
            ajax($data);
        }

        $code = trim(input('verify'));


        //判断当前用户的短信验证码是否正确
        $where = array('addtime'=>array('gt',time()-600));
        $verify = db('code')->where("mobile=$mobile")->where($where)->field('code')->order('id desc')->limit(1)->find();


        if($code == $verify['code']) {//验证码正确

            $memberData['password'] = Hash::make((string)$password);
            $memberData['update_time'] = time();


            if(db('admin_user')->where("id={$memberInfo['id']}")->update($memberData)) {
                $data = array('code'=>0,'msg'=>'修改成功');  
            }else{
                $data = array('code'=>1,'msg'=>'修改失败');  
            }

        }else{//验证码错误
            $data = array('code'=>2,'msg'=>'验证码有误或超时，请重新获取');  
        }

        ajax($data);
    }

}
