<?php

namespace App\Lib;

use App\Model\AreasModel;
use App\Services\AreasService;

class AddressCorrect
{
    private $SydApi;

    public function __construct()
    {
        $this->SydApi = new SydApi();
    }

    public function completionState($address, $country): array
    {
        if (empty($address)) {
            return [];
        }
        $data = $this->getContactInfo($address, $country);
        if (!empty($data)) {
            $state = AreasModel::where('type', '=', '2')
                ->where('name', 'like', '%'.$data['state'].'%')
                ->where('status', '=', '1')
                ->value('name');
            if (!empty($state)) {
                $data['state'] = $state;
            }
        }
        if (!is_array($data)) {
            return [];
        }

        return $data;
    }

    public function getContactInfo($address, $country = 'CN')
    {
        return $this->bySydContact($address, $country);
    }


    private function bySydContact($address, $country = 'CN')
    {
        //接口解析
        $ret = $this->SydApi->parseContacts($country, $address);

        if (!is_array($ret)) {
            return false;
        }
        //生成最终所需格式
        $parsArray = [];
        foreach ($ret['source'] as $key => $itemRet) {
            if ($key == 'province') {
                $parsArray['state'] = $itemRet['name'];
            }
            if ($key == 'city') {
                $parsArray['city'] = $itemRet['name'];
            }

            if ($key == 'area') {
                $parsArray['district'] = $itemRet['name'];
            }


            if ($key == 'street') {
                $parsArray['street'] = $itemRet['name'];
            }


            if ($key == 'detail') {
                $parsArray['detail'] = ($parsArray['street'] ?? '').$itemRet;
            }
            if ($key == 'phone') {
                $parsArray['phone'] = $itemRet;
            }
            if ($key == 'name') {
                $parsArray['name'] = $itemRet;
            }
        }

        return $parsArray;
    }


    /**
     * 获取启用国家信息
     */
    public function countryAddressInfo()
    {
        $where   = [];
        $where[] = ['status', '=', '1'];
        $data    = AreasModel::where($where)->get()->toArray();
        $data    = $this->recursive_make_tree(
            $data, $pk = 'id', $pid = 'parent_id', $child = '_child', $root = 0
        );

        return $data;
    }

    /**
     * @DOC 层级数组
     */
    public function recursive_make_tree($list, $pk = 'id', $pid = 'pid',
        $child = '_child', $root = 0
    ) {
        $tree = [];
        foreach ($list as $key => $val) {
            if ($val[$pid] == $root) {
                //获取当前$pid所有子类
                unset($list[$key]);
                if (!empty($list)) {
                    $child = self::recursive_make_tree(
                        $list, $pk, $pid, $child, $val[$pk]
                    );
                    if (!empty($child)) {
                        $val['_child'] = $child;
                    }
                }
                $tree[] = $val;
            }
        }

        return $tree;
    }


    /**
     * 处理地址数组，获取省市区信息
     *
     * @param $addressArr
     */
    public function getRegionInfo(&$addressArr, $country = 'CN')
    {
        $AreasService   = make(AreasService::class);
        $areasChinaList = $AreasService->listChina();

        //遍历处理
        foreach ($addressArr as $key => $addressItem) {
            $addressArr[$key]['ret'] = $this->manageSingleAddress(
                $addressItem['key'], $areasChinaList
            );
        }

        return $addressArr;
    }


    /**
     * 处理单条地址
     *
     * @param $address
     * @param $areasChinaList
     *
     * @return array
     */
    private function manageSingleAddress(&$address, &$areasChinaList)
    {
        //返回结果
        $ret = array("state" => '', "city" => '', "district" => '');

        //获取省份列表,并获取省份信息
        $stateList = $this->getStateList($areasChinaList);
        $stateInfo = $this->getStateInfo($address, $stateList);
        if (empty($stateInfo)) {
            return $ret;
        } else {
            $ret['state'] = $stateInfo['name'];
        }

        //获取市列表,并获取市信息
        $cityList = $this->getCityList($areasChinaList, $stateInfo['id']);
        $cityInfo = $this->getCityInfo($stateInfo, $address, $cityList);
        if (empty($cityInfo)) {
            return $ret;
        } else {
            $ret['city'] = $cityInfo['name'];
        }

        //获取区列表，并获取区信息
        $districtList = $this->getDistrictList(
            $areasChinaList, $cityInfo['id']
        );
        $districtInfo = $this->getDistrictInfo(
            $cityInfo, $address, $districtList
        );
        if (!empty($districtInfo)) {
            $ret['district'] = $districtInfo['name'];
        }

        // 处理详细地址
//        $address       = preg_replace('/' . $ret['state'] . '/', '', $address, 1);
//        $address       = preg_replace('/' . $ret['city'] . '/', '', $address, 1);
//        $address       = preg_replace('/' . $ret['district'] . '/', '', $address, 1);
//        $ret['detail'] = $address;
        //返回结果
        return $ret;
    }


    /**
     * 获取省级列表
     *
     * @param $regionList
     *
     * @return array
     */
    private function getStateList(&$regionList)
    {
        $retList = array();
        foreach ($regionList as $regionItem) {
            if ($regionItem['type'] == 2) {
                $retList[] = $regionItem;
            }
        }

        return $retList;
    }

    /**
     * 获取地址中省信息
     *
     * @param $address
     * @param $stateList
     *
     * @return array
     */
    private function getStateInfo(&$address, &$stateList)
    {
        //取出所有匹配到的省份
        $stateInfoTmp = array();
        foreach ($stateList as $stateItem) {
            //第一次直接匹配到
            $pos = strpos($address, $stateItem['name']);
            if ($pos !== false) {
                $stateInfoTmp[$pos] = array(
                    "arr" => $stateItem,
                );
                continue;
            }

            //第二次剔除附加词再次匹配
            $stateTmp = $this->filterState($stateItem['name']);
            $pos      = strpos($address, $stateTmp);
            if ($pos !== false) {
                $stateInfoTmp[$pos] = array(
                    "arr" => $stateItem,
                );
            }
        }

        //匹配最终省份
        $stateInfo = array();
        if (!empty($stateInfoTmp)) {
            ksort($stateInfoTmp);
            foreach ($stateInfoTmp as $pos => $stateInfoTmpItem) {
                $stateInfo = array(
                    "pos"  => $pos,
                    "name" => $stateInfoTmpItem['arr']['name'],
                    "id"   => $stateInfoTmpItem['arr']['id'],
                );
                break;
            }
        }

        return $stateInfo;
    }

    /**
     * 获取市级列表
     *
     * @param $regionList
     * @param $stateId
     *
     * @return array
     */
    private function getCityList(&$regionList, $stateId)
    {
        $retList = array();
        foreach ($regionList as $regionItem) {
            if ($regionItem['type'] == 3
                && $regionItem['parent_id'] == $stateId
            ) {
                $retList[] = $regionItem;
            }
        }

        return $retList;
    }

    /**
     * 获取地址中市信息
     *
     * @param $stateInfo
     * @param $address
     * @param $cityList
     *
     * @return array
     */
    private function getCityInfo(&$stateInfo, &$address, &$cityList)
    {
        //取出所有匹配到的市
        $cityInfoTmp = array();
        foreach ($cityList as $cityItem) {
            //第一次直接匹配到
            $pos = strpos($address, $cityItem['name']);
            if ($pos !== false) {
                $cityInfoTmp[$pos] = array(
                    "arr" => $cityItem,
                );
                continue;
            }

            //第二次剔除附加词再次匹配
            $cityTmp = str_replace('市', '', $cityItem['name']);
            $pos     = strpos($address, $cityTmp);
            if ($pos !== false) {
                $cityInfoTmp[$pos] = array(
                    "arr" => $cityItem,
                );
            }
        }

        //判断是否匹配到省
        if (isset($cityInfoTmp[$stateInfo['pos']])) {
            unset($cityInfoTmp[$stateInfo['pos']]);
        }

        //匹配最终市
        $cityInfo = array();
        if (!empty($cityInfoTmp)) {
            ksort($cityInfoTmp);
            foreach ($cityInfoTmp as $pos => $cityInfoTmpItem) {
                $cityInfo = array(
                    "pos"  => $pos,
                    "name" => $cityInfoTmpItem['arr']['name'],
                    "id"   => $cityInfoTmpItem['arr']['id'],
                );
                break;
            }
        }

        return $cityInfo;
    }

    /**
     * 获取区级列表
     *
     * @param $regionList
     * @param $cityId
     *
     * @return array
     */
    private function getDistrictList(&$regionList, $cityId)
    {
        $retList = array();
        foreach ($regionList as $regionItem) {
            if ($regionItem['type'] == 4
                && $regionItem['parent_id'] == $cityId
            ) {
                $retList[] = $regionItem;
            }
        }

        return $retList;
    }

    /**
     * 获取地址中区信息
     *
     * @param $cityInfo
     * @param $address
     * @param $districtList
     *
     * @return array
     */
    private function getDistrictInfo(&$cityInfo, &$address, &$districtList)
    {
        //取出所有匹配到的区
        $districtInfoTmp = array();
        foreach ($districtList as $districtItem) {
            //第一次直接匹配到
            $pos = strpos($address, $districtItem['name']);
            if ($pos !== false) {
                $districtInfoTmp[$pos] = array(
                    "arr" => $districtItem,
                );
                continue;
            }

            //第二次剔除附加词再次匹配
            $districtTmp = str_replace('区', '', $districtItem['name']);
            $pos         = strpos($address, $districtTmp);
            if ($districtTmp == '中') {
                $pos1 = strpos($address, '中国');
            } else {
                $pos1 = 99999999;
            }
            if ($pos !== false && $pos !== $pos1) {
                $districtInfoTmp[$pos] = array(
                    "arr" => $districtItem,
                );
            }
        }

        //判断是否匹配到市
        if (isset($districtInfoTmp[$cityInfo['pos']])) {
            unset($districtInfoTmp[$cityInfo['pos']]);
        }

        //匹配最终区
        $districtInfo = array();
        if (!empty($districtInfoTmp)) {
            ksort($districtInfoTmp);
            foreach ($districtInfoTmp as $districtInfoTmpItem) {
                $districtInfo = $districtInfoTmpItem['arr'];
                break;
            }
        }

        return $districtInfo;
    }

    /**
     * 省剔除附加词
     *
     * @param $region
     *
     * @return mixed
     */
    private function filterState($region)
    {
        $stateSearchArr  = array('省', '壮族', '回族', '特别', '行政区',
                                 '自治区', '维吾尔');
        $stateReplaceArr = array('', '', '', '', '', '', '');

        return str_replace($stateSearchArr, $stateReplaceArr, $region);
    }
}
