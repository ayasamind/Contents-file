<?php

namespace ContentsFile\Controller;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Utility\Inflector;
use ContentsFile\Controller\AppController;
use ContentsFile\Controller\Traits\NormalContentsFileControllerTrait;
use ContentsFile\Controller\Traits\S3ContentsFileControllerTrait;

class ContentsFileController extends AppController
{
    use S3ContentsFileControllerTrait;
    use NormalContentsFileControllerTrait;
    private $baseModel;

    /**
     * loader
     * @author hagiwara
     */
    public function loader()
    {
        $this->autoRender = false;

        //Entityに接続して設定値を取得
        $this->baseModel = TableRegistry::get($this->request->query['model']);

        // このレベルで切り出す
        $fieldName = $this->request->query['field_name'];
        if (!empty($this->request->query['tmp_file_name'])) {
            $filename = $this->request->query['tmp_file_name'];
            $filepath = $this->{Configure::read('ContentsFile.Setting.type') . 'TmpFilePath'}($filename);
            Configure::read('ContentsFile.Setting.Normal.tmpDir') . $filename;
        } elseif (!empty($this->request->query['model_id'])) {
            //表示条件をチェックする
            $checkMethodName = 'contentsFileCheck' . Inflector::camelize($fieldName);
            if (method_exists($this->baseModel, $checkMethodName)) {
                //エラーなどの処理はTableに任せる
                $this->baseModel->{$checkMethodName}($this->request->query['model_id']);
            }
            //attachementからデータを取得
            $attachmentModel = TableRegistry::get('Attachments');
            $attachmentData = $attachmentModel->find('all')
                ->where(['model' => $this->request->query['model']])
                ->where(['model_id' => $this->request->query['model_id']])
                ->where(['field_name' => $this->request->query['field_name']])
                ->first()
            ;
            if (empty($attachmentData)) {
                throw new NotFoundException('404 error');
            }
            $filename = $attachmentData->file_name;
            $filepath = $this->{Configure::read('ContentsFile.Setting.type') . 'FilePath'}($attachmentData);

            //通常のセットの時のみresize設定があれば見る
            if (!empty($this->request->query['resize'])) {
                $filepath = $this->{Configure::read('ContentsFile.Setting.type') . 'ResizeSet'}($filepath, $this->request->query['resize']);
            }
        }

        $this->{Configure::read('ContentsFile.Setting.type') . 'Loader'}($filename, $filepath);
    }

    /**
     * getFileType
     * @author hagiwara
     * @param string $ext
     */
    private function getFileType($ext)
    {
        $aContentTypes = [
            'txt'=>'text/plain',
            'htm'=>'text/html',
            'html'=>'text/html',
            'jpg'=>'image/jpeg',
            'jpeg'=>'image/jpeg',
            'gif'=>'image/gif',
            'png'=>'image/png',
            'bmp'=>'image/x-bmp',
            'ai'=>'application/postscript',
            'psd'=>'image/x-photoshop',
            'eps'=>'application/postscript',
            'pdf'=>'application/pdf',
            'swf'=>'application/x-shockwave-flash',
            'lzh'=>'application/x-lha-compressed',
            'zip'=>'application/x-zip-compressed',
            'sit'=>'application/x-stuffit'
        ];
        $sContentType = 'application/octet-stream';

        if (!empty($aContentTypes[$ext])) {
            $sContentType = $aContentTypes[$ext];
        }
        return $sContentType;
    }

    /**
     * getMimeType
     * @author hagiwara
     * @param string $filename
     */
    private function getMimeType($filename)
    {
        $aContentTypes = [
            'txt'=>'text/plain',
            'htm'=>'text/html',
            'html'=>'text/html',
            'jpg'=>'image/jpeg',
            'jpeg'=>'image/jpeg',
            'gif'=>'image/gif',
            'png'=>'image/png',
            'bmp'=>'image/x-bmp',
            'ai'=>'application/postscript',
            'psd'=>'image/x-photoshop',
            'eps'=>'application/postscript',
            'pdf'=>'application/pdf',
            'swf'=>'application/x-shockwave-flash',
            'lzh'=>'application/x-lha-compressed',
            'zip'=>'application/x-zip-compressed',
            'sit'=>'application/x-stuffit'
        ];
        $sContentType = 'application/octet-stream';

        if (($pos = strrpos($filename, ".")) !== false) {
            // 拡張子がある場合
            $ext = strtolower(substr($filename, $pos + 1));
            if (strlen($ext)) {
                return $aContentTypes[$ext] ? $aContentTypes[$ext] : $sContentType;
            }
        }
        return $sContentType;
    }

}