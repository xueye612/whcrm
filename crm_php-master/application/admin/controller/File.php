<?php
// +----------------------------------------------------------------------
// | Description: 附件
// +----------------------------------------------------------------------
// | Author:  Michael_xu | gengxiaoxu@5kcrm.com
// +----------------------------------------------------------------------
namespace app\admin\controller;

use app\work\traits\WorkAuthTrait;
use think\Hook;
use think\Request;

class File extends ApiCommon
{
    use WorkAuthTrait;

	/**
     * 用于判断权限
     * @permission 无限制
     * @allow 登录用户可访问
     * @other 其他根据系统设置
    **/    
    public function _initialize()
    {
        $action = [
            'permission'=>[''],
            'allow'=>['index', 'save', 'delete', 'update', 'read', 'download', 'deleteall', 'downloadimage']
        ];
        Hook::listen('check_auth',$action);
        $request = Request::instance();
        $a = strtolower($request->action());        
        if (!in_array($a, $action['permission'])) {
            parent::_initialize();
        }         
    }

    /**
     * 附件列表
     * @author Michael_xu
     * @param 
     * @return                            
     */
    public function index()
    {
        $fileModel = model('File');
        $param = $this->param;
        $data = $fileModel->getDataList($param, $param['by']);
        return resultArray(['data' => $data]);
    }

	/**
     * 附件上传
     * @author Michael_xu
     * @return                            
     */
    public function save()
    {
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: POST');
        header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept");

        $fileModel = model('File');
        $userId = $this->userInfo['id'];

        # 项目附件上传鉴权：必须在保存物理文件、写 admin_file、写 work_task_file 之前完成。
        # module=work_task 即视为项目附件，必须同时满足：work_id>0、task(module_id)>0、
        # task 真实属于该 work_id、当前用户拥有 uploadTaskFile 权限。任一不满足直接拒绝。
        if (!empty($this->param['module']) && $this->param['module'] == 'work_task') {
            $workId = (int)($this->param['work_id'] ?? 0);
            $taskId = (int)($this->param['module_id'] ?? 0);
            if ($workId <= 0) {
                return resultArray(['error' => '项目附件必须指定所属项目']);
            }
            if ($taskId <= 0) {
                return resultArray(['error' => '缺少任务参数，无法上传项目附件']);
            }
            # 查询任务真实 work_id，校验任务确实属于该项目（防止伪造/跨项目 task_id）
            $realWorkId = $fileModel->getTaskWorkId($taskId);
            if ($realWorkId <= 0 || $realWorkId !== $workId) {
                return resultArray(['error' => '任务不属于当前项目，无法上传附件']);
            }
            # 权限：项目「任务添加附件」
            if (!$this->checkWorkOperationAuth('uploadTaskFile', $workId, $userId)) {
                return resultArray(['error' => '无权上传该附件']);
            }
        }

        $type     = $this->param['type'];
        $files    = request()->file('file');
        $imgs     = request()->file('img');
        $i        = 0;
        $newFiles = [];

        if (!empty($type) && in_array($type, ['img', 'file'])) {
            # todo 兼容11.0前端
            if ($type == 'img') {
                $newFiles[0]['obj']   = $files;
                $newFiles[0]['types'] = 'img';
            }
            if ($type == 'file') {
                $newFiles[0]['obj']   = $files;
                $newFiles[0]['types'] = 'file';
            }
        } else {
            # todo 兼容9.0前端
            if (!empty($files)) {
                foreach ($files as $v) {
                    $newFiles[$i]['obj']   = $v;
                    $newFiles[$i]['types'] = 'file';
                    $i++;
                }
            }
            if (!empty($imgs)) {
                foreach ($imgs as $v) {
                    $newFiles[$i]['obj']   = $v;
                    $newFiles[$i]['types'] = 'img';
                    $i++;
                }
            }
        }


        $param = $this->param;
        $param['create_user_id'] = $userId;
        $res = $fileModel->createData($newFiles, $param);
		if($res){
			return resultArray(['data' => $res]);
		} else {
			return resultArray(['error' => $fileModel->getError()]);
		}

    }

    /**
     * 附件删除
     * @author Michael_xu
     * @param 通过 save_name 作为条件 来删除附件
     * @return                            
     */ 
    public function delete()
    {
        $fileModel = model('File');
        $param = $this->param;
        $userId = $this->userInfo['id'];

        # 1. 解析并校验 file_id / save_name 一致性（两者同时给且指向不同附件则拒绝）
        list($idOk, $fileId, $idErr) = $fileModel->resolveFileId($param);
        if (!$idOk) {
            return resultArray(['error' => $idErr]);
        }

        $workId = (int)($param['work_id'] ?? 0);

        # 2. 后端依据真实 work_task_file 关联判定是否项目附件（不依赖前端是否传 work_id）
        if ($fileModel->hasWorkTaskRelation($fileId)) {
            # 项目附件：work_id 必须存在、必须归属本项目、必须有 deleteTaskFile 权限
            if ($workId <= 0) {
                return resultArray(['error' => '项目附件必须指定所属项目']);
            }
            if (!$this->checkWorkOperationAuth('deleteTaskFile', $workId, $userId)) {
                return resultArray(['error' => '无权删除该附件']);
            }
            if (!$fileModel->isWorkTaskFileInProject($fileId, $workId)) {
                return resultArray(['error' => '该附件不属于当前项目，无法删除']);
            }
            $res = $fileModel->deleteWorkTaskFileInProject($fileId, $workId);
            if (!$res) {
                return resultArray(['error' => $fileModel->getError()]);
            }
            return resultArray(['data' => '删除成功']);
        }

        # 3. 非项目附件：若请求声称是项目附件（传 work_id 或 module=work_task）则拒绝，防止伪造 module 操作
        $claimedProject = $workId > 0 || (isset($param['module']) && $param['module'] === 'work_task');
        if ($claimedProject) {
            return resultArray(['error' => '该附件不属于任何项目，无法按项目附件删除']);
        }

        # 4. 真正的非项目附件（CRM/OA 等）保留原有通用流程
        $res = $fileModel->delFileBySaveName($param['save_name'], $param);
        if (!$res) {
            return resultArray(['error' => $fileModel->getError()]);
        }
        return resultArray(['data' => '删除成功']);        
    }

    /**
     * 全部删除(活动、产品)
     *
     * @return \think\response\Json
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     */
    public function deleteAll()
    {
        if ((empty($this->param['module']) && empty($this->param['module_id'])) || empty($this->param['file_id'])) {
            return resultArray(['error' => '参数错误！']);
        }

        $fileModel = new \app\admin\model\File();

        if (!$fileModel->deleteAll($this->param)) return resultArray(['error' => '操作失败！']);

        return resultArray(['data' => '操作成功！']);
    }

    /**
     * 附件编辑
     */
    public function update()
    {
        $fileModel = model('File');
        $param = $this->param;
        $userId = $this->userInfo['id'];

        # 1. 解析并校验 file_id / save_name 一致性
        list($idOk, $fileId, $idErr) = $fileModel->resolveFileId($param);
        if (!$idOk) {
            return resultArray(['error' => $idErr]);
        }

        $workId = (int)($param['work_id'] ?? 0);

        # 2. 项目附件（真实 work_task_file 关联）：重命名与删除执行相同的归属、权限检查
        if ($fileModel->hasWorkTaskRelation($fileId)) {
            if ($workId <= 0) {
                return resultArray(['error' => '项目附件必须指定所属项目']);
            }
            if (!$this->checkWorkOperationAuth('deleteTaskFile', $workId, $userId)) {
                return resultArray(['error' => '无权重命名该附件']);
            }
            if (!$fileModel->isWorkTaskFileInProject($fileId, $workId)) {
                return resultArray(['error' => '该附件不属于当前项目，无法重命名']);
            }
        } else {
            # 非项目附件：若请求声称是项目附件则拒绝，防止伪造 module 越权重命名
            $claimedProject = $workId > 0 || (isset($param['module']) && $param['module'] === 'work_task');
            if ($claimedProject) {
                return resultArray(['error' => '该附件不属于任何项目，无法按项目附件重命名']);
            }
        }

        if ( $param['save_name'] && $param['name'] ) {
            $ret = $fileModel->updateNameBySaveName($param['save_name'],$param['name']);
            if ($ret) {
                return resultArray(['data'=>'操作成功']);
            } else {
                return resultArray(['error'=>'操作失败']);
            } 
        } else {
            return resultArray(['error'=>'参数错误']);
        }
    }

	/**
     * 附件查看（下载）
     * @author Michael_xu
     * @return                            
     */  
    public function read()
    {
        $fileModel = model('File');
        $param = $this->param;
        $data = $fileModel->getDataBySaveName($param['save_name']);
        if (!$data) {
            return resultArray(['error' => $this->getError()]);
        }
        return resultArray(['data' => $data]);        
    }   
    
    /**
     * 静态资源文件下载
     */
    public function download()
    {
        if(isset($this->param['path'])){
            $path = $this->param['path'];
            $name = $this->param['name'] ?: '';
            if (empty($path)) return resultArray(['error' => '参数错误！']);
            return download(realpath('./public/' . $path), $name);
        }else{
            $path = $this->param['save_name'];
            $name = $this->param['name'] ?: '';
            if (empty($path)) return resultArray(['error' => '参数错误！']);
            if (!strstr($path, 'uploads')) $path = 'uploads/' . $path;
            return download(realpath('./public/' . $path), $name);
        }
    }

    /**
     * 下载图片（头像），前端要求重写一个。
     *
     * @return \think\response\Json|void
     */
    public function downloadImage()
    {
        $path = $this->param['path'];
        $file = explode('public/', $path);

        if (empty($path) || empty($file[1])) return resultArray(['error' => '参数错误！']);

        return download(realpath('./public/'.$file[1]));
    }
}
