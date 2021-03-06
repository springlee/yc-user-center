<?php

namespace Yc\UserCenter;

use GuzzleHttp\Client;
use Yc\UserCenter\Exceptions\HttpException;
use Yc\UserCenter\Exceptions\InvalidArgumentException;
use Yc\UserCenter\Exceptions\RedisException;

class Authorization
{

    protected $config;

    protected $guzzleOptions = [];

    protected $token;

    protected $openid;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    private function getHttpClient()
    {
        return new Client($this->guzzleOptions);
    }


    /**
     * 设置guzzle 请求头
     * @param array $options
     * @return $this
     */
    public function setGuzzleOptions(array $options)
    {
        $this->guzzleOptions = $options;
        return $this;
    }


    private function getRedisClient()
    {
        $redisConfig = $this->config['redis'] ?? [];
        return new \Predis\Client($redisConfig, $redisConfig['options']??[]);
    }


    /**
     * 设置token
     * @param $token
     * @return $this
     */
    public function setToken($token)
    {
        $this->token = $token;
        return $this;
    }

    /**
     * 设置openid
     * @param $openid
     * @return $this
     */
    public function setOpenid($openid)
    {
        $this->openid = $openid;
        return $this;
    }

    private function getUrl()
    {
        return $this->config['backend_url'] ?? '';
    }

    private function getRedisPrefix()
    {
        return $this->config['redis']['prefix'] ?? '';
    }

    /**
     * 用户注销
     * @return mixed
     * @throws HttpException
     */
    public function logout()
    {
        try {
            $url = $this->getUrl() . '/api/authorization/logout';
            $response = $this->getHttpClient()->get($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            ])->getBody()->getContents();
            return \json_decode($response, true);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 用户登录
     * @param $username
     * @param $password
     * @param $reservedTerminal
     * @return mixed
     * @throws HttpException
     */
    public function login($username, $password, $reservedTerminal = 0)
    {
        $body = [
            'username' => $username,
            'password' => $password,
            'reserved_terminal' => $reservedTerminal,
        ];
        try {
            $url = $this->getUrl() . '/api/authorization/login';
            $response = $this->getHttpClient()->post($url, [
                'form_params' => $body,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            ])->getBody()->getContents();
            return \json_decode($response, true);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * 获取code
     * @return mixed
     * @throws HttpException
     * @throws InvalidArgumentException
     */
    public function getCode()
    {
        $body = array_filter([
            'app_key' => $this->config['app_key'],
            'app_secret' => $this->config['app_secret'],
        ]);
        try {
            $url = $this->getUrl() . '/api/authorization/client_code';
            $response = $this->getHttpClient()->post($url, [
                'form_params' => $body,
            ])->getBody()->getContents();
            return \json_decode($response, true);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * 获取用户
     * @return string
     * @throws HttpException
     */
    public function getUser()
    {
        $body = array_filter([
            'app_key' => $this->config['app_key'],
        ]);
        try {
            $url = $this->getUrl() . '/api/authorization/user';
            $response = $this->getHttpClient()->post($url, [
                'form_params' => $body,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            ])->getBody()->getContents();
            return \json_decode($response, true);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * 判断是否可以访问
     * @param $uri
     * @return array
     * @throws HttpException
     */
    public function canVisit($uri)
    {
        $body = array_filter([
            'app_key' => $this->config['app_key'],
            'uri' => $uri,
        ]);
        try {
            $url = $this->getUrl() . '/api/authorization/can_visit';
            $response = $this->getHttpClient()->post($url, [
                'form_params' => $body,
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->token
                ]
            ])->getBody()->getContents();
            return \json_decode($response, true);
        } catch (\Exception $e) {
            throw new HttpException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * 判断是否可以访问(直接走缓存）
     * @param $uri
     * @return array
     * @throws RedisException
     */
    public function canVisitByCache($uri)
    {
        $response = [
            'code' => 1,
            'state'=>'000001',
            'msg' => '允许访问',
        ];
        $prefix = 'zlj_oa_database_';
        $key = $prefix . $this->openid . '-' . $this->config['app_key'];
        try {
            $exist = $this->getRedisClient()->exists($key);
            if (!$exist) {
                $response['code'] = 401;
                $response['state'] = '000401';
                $response['msg'] = '鉴权失败';
            } else {
                $clientPermissionsAndMenuTree = $this->getRedisClient()->get($key);
                $clientPermissionsAndMenuTree = \json_decode($clientPermissionsAndMenuTree, true);
                if (!$clientPermissionsAndMenuTree || (!$clientPermissionsAndMenuTree['user']['is_super_admin'] && !in_array($uri, $clientPermissionsAndMenuTree['permissions']))) {
                    $response['code'] = 403;
                    $response['state'] = '000403';
                    $response['msg'] = '禁止访问';
                }
            }
            return $response;
        } catch (\Exception $e) {
            throw new RedisException($e->getMessage(), $e->getCode(), $e);
        }
    }

    /**
     * Desc: 判断是否登录
     * @return array
     * @throws RedisException
     */
    public function checkIsLoginByCache()
    {
        $response = [
            'state'=>'000001',
            'code' => 1,
            'msg' => '在线',
        ];
        $prefix = $this->getRedisPrefix();
        $key = $prefix . $this->token;
        try {
            $exist = $this->getRedisClient()->exists($key);
            if (!$exist) {
                $response['code'] = 401;
                $response['state'] = '000401';
                $response['msg'] = '鉴权失败';
            }
            return $response;
        } catch (\Exception $e) {
            throw new RedisException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * Desc: 获取简单的用户信息
     * @return array
     * @throws RedisException
     */
    public function getSimpleUserByCache()
    {
        $response = [
            'code' => 1,
            'state'=>'000001',
            'msg' => '获取用户成功',

        ];
        $prefix = $this->getRedisPrefix();
        $key = $prefix . $this->token;
        try {
            $exist = $this->getRedisClient()->exists($key);
            if (!$exist) {
                $response['code'] = 401;
                $response['state'] = '000401';
                $response['msg'] = '鉴权失败';
            }else{
                $user = $this->getRedisClient()->get($key);
                $response['data'] = json_decode($user,true);
            }
            return $response;
        } catch (\Exception $e) {
            throw new RedisException($e->getMessage(), $e->getCode(), $e);
        }
    }


    /**
     * 获取 code 的后的跳转前端地址
     * @param $code
     * @return string
     */
    public function getFrontendUrlByCode($code)
    {
        return $this->config['frontend_url'] . '?' . http_build_query(['code' => $code]);
    }
}
