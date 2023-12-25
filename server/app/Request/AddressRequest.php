<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class AddressRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'realname'   => 'required',
            'phone'      => 'required',
            'country'    => 'required',
            //            'province'   => 'required',
            //            'city'       => 'required',
            'district'   => 'required',
            'address'    => 'required',
            'area_code'  => 'required',
            'zip'        => 'required',
            'is_default' => 'required',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息
     */
    public function messages(): array
    {
        $required_msg = ':attribute必填';

        return [
            'realname.required'   => $required_msg,
            'phone.required'      => $required_msg,
            'country.required'    => $required_msg,
            //            'province.required'   => $required_msg,
            //            'city.required'       => $required_msg,
            'district.required'   => $required_msg,
            'address.required'    => $required_msg,
            'area_code.required'  => $required_msg,
            'zip.required'        => $required_msg,
            'is_default.required' => $required_msg,
        ];
    }

    /**
     * 获取验证错误的自定义属性
     */
    public function attributes(): array
    {
        return [
            'realname'   => '姓名',
            'phone'      => '手机号',
            'country'    => '国家',
            //            'province'   => '省份',
            //            'city'       => '城市',
            'district'   => '区域',
            'address'    => '详细地址',
            'zip'        => '邮编',
            'area_code'  => '国际区号',
            'is_default' => '是否为默认地址',
        ];
    }

}
