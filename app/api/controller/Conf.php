<?php

namespace app\api\controller;

use think\App;
use app\api\BaseController;
use app\model\Conf as ConfModel;

class Conf extends BaseController
{
    public function __construct(App $app)
    {
        parent::__construct($app);
        //筛选字段
        $this->searchFilter = [
            "conf_id" => "=", //相同筛选
            "conf_key" => "like", //相似筛选
            "conf_value" => "like", //相似筛选
            "conf_desc" => "like", //相似筛选
            "conf_readonly" => "=", //相似筛选
        ];
        $this->insertFields = [
            "conf_key", "conf_value", "conf_desc", "conf_readonly"
        ];
        $this->updateFields = [
            "conf_key", "conf_value", "conf_desc", "conf_readonly"
        ];
        $this->insertRequire = [
            'conf_key' => "配置名称必须填写"
        ];
        $this->updateRequire = [
            'conf_key' => "配置名称必须填写"
        ];
        $this->model = new ConfModel();
    }

    public function add()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        foreach ($this->insertRequire as $k => $v) {
            if (!input($k)) {
                jerr($v);
            }
        }
        $data = [];
        foreach (input() as $k => $v) {
            if (in_array($k, $this->insertFields)) {
                $data[$k] = $v;
            }
        }
        $data[$this->table . "_updatetime"] = time();
        $data[$this->table . "_createtime"] = time();
        $this->model->insert($data);
        jok('用户添加成功');
    }
    public function update()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!input($this->pk)) {
            jerr($this->pk . "必须填写");
        }
        if (!isInteger($this->pk_value)) {
            jerr("修改失败,参数错误");
        }
        $map[$this->pk] = $this->pk_value;
        $item = $this->model->where($map)->find();
        if (empty($item)) {
            jerr("数据查询失败");
        }
        foreach ($this->updateRequire as $k => $v) {
            if (!input($k)) {
                jerr($v);
            }
        }
        $data = input($this->updateFields);
        $data[$this->table . "_updatetime"] = time();
        if ($item[$this->table . "_system"] == 1) {
            unset($data[$this->table . "_key"]);
        }
        if ($item[$this->table . "_readonly"] == 1) {
            unset($data[$this->table . "_key"]);
            unset($data[$this->table . "_value"]);
        }
        $this->model->where($this->pk, $this->pk_value)->update($data);
        jok('配置信息更新成功');
    }

    /**
     * 禁用用户
     *
     * @return void
     */
    public function disable()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!input($this->pk)) {
            jerr($this->pk . "参数必须填写");
        }
        if (isInteger($this->pk_value)) {
            $map = [$this->pk => $this->pk_value];
            $item = $this->model->where($map)->find();
            if (empty($item)) {
                jerr("数据查询失败");
            }
            if ($item[$this->table . "_system"] == 1) {
                jerr("系统配置不允许操作！");
            }
            $this->model->where($map)->where($this->pk . " > 1")->update([
                $this->table . "_status" => 1,
                $this->table . "_updatetime" => time(),
            ]);
        } else {
            $list = explode(',', $this->pk_value);
            $this->model->where($this->pk, 'in', $list)->where($this->table . "_system", 0)->update([
                $this->table . "_status" => 1,
                $this->table . "_updatetime" => time(),
            ]);
        }
        jok("禁用配置成功");
    }

    /**
     * 启用用户
     *
     * @return void
     */
    public function enable()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!input($this->pk)) {
            jerr($this->pk . "参数必须填写");
        }
        if (isInteger($this->pk_value)) {
            $map = [$this->pk => $this->pk_value];
            $item = $this->model->where($map)->find();
            if (empty($item)) {
                jerr("数据查询失败");
            }
            if ($item[$this->table . "_system"] == 1) {
                jerr("系统配置不允许操作！");
            }
            $this->model->where($map)->where($this->pk . " > 1")->update([
                $this->table . "_status" => 0,
                $this->table . "_updatetime" => time(),
            ]);
        } else {
            $list = explode(',', $this->pk_value);
            $this->model->where($this->pk, 'in', $list)->where($this->table . "_system", 0)->update([
                $this->table . "_updatetime" => time(),
            ]);
        }
        jok("启用配置成功");
    }

    /**
     * 删除用户
     *
     * @return void
     */
    public function delete()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!input($this->pk)) {
            jerr($this->pk . "必须填写");
        }
        if (isInteger($this->pk_value)) {
            $map = [$this->pk => $this->pk_value];
            $item = $this->model->where($map)->find();
            if (empty($item)) {
                jerr("数据查询失败");
            }
            if ($item[$this->table . "_system"] == 1) {
                jerr("系统配置不允许操作！");
            }
            $this->model->where($map)->where($this->table . "_system", 0)->delete();
        } else {
            $list = explode(',', $this->pk_value);
            $this->model->where($this->pk, 'in', $list)->where($this->table . "_system", 0)->delete();
            //删除对应ID的授权记录
            foreach ($list as $item) {
                $conf = $this->model->where("conf_id", $item)->find();
                if ($conf[$this->table . "_system"] == 1) {
                    continue;
                }
            }
        }
        jok('删除配置成功');
    }

    /**
     * 为配置授权节点
     *
     * @return void
     */
    public function authorize()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        if (!input($this->pk)) {
            jerr($this->pk . "必须填写");
        }
        if (!isInteger($this->pk_value)) {
            jerr("修改失败,参数错误");
        }
        $map[$this->pk] = $this->pk_value;
        $item = $this->model->where($map)->find();
        if (empty($item)) {
            jerr("数据查询失败");
        }
        $this->authModel->where([
            "auth_conf" => $this->pk_value
        ])->delete();
        if ($item[$this->pk] == 1) {
            jerr("超级管理组无需授权！");
        }
        $node_ids = explode(",", input("node_ids"));
        foreach ($node_ids as $node_id) {
            if (intval($node_id) == 0) {
                continue;
            }
            $this->authModel->insert([
                "auth_conf" => $this->pk_value,
                "auth_node" => $node_id,
                "auth_createtime" => time(),
                "auth_updatetime" => time()
            ]);
        }
        jok('配置授权成功');
    }
    /**
     * 读取基本配置
     *
     * @return void
     */
    public function getBaseConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        $datalist = $this->model->where('conf_key', 'in', 'app_name')->order($this->pk . " asc")->select();
        return jok('', $datalist);
    }
    /**
     * 更新基础配置
     *
     * @return void
     */
    public function updateBaseConfig()
    {
        $error = $this->access();
        if ($error) {
            return $error;
        }
        foreach (input("post.") as $k => $v) {
            $map["conf_key"] = $k;
            $item = $this->model->where($map)->find();
            if (empty($item)) {
                continue;
            }
            if ($item[$this->table . "_readonly"] == 1) {
                continue;
            }
            $this->model->where("conf_key", $k)->update(["conf_value" => $v]);
        }
        jok("配置修改成功");
    }
}
