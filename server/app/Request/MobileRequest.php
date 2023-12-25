<?php

declare(strict_types=1);

namespace App\Request;

use Hyperf\Validation\Request\FormRequest;

class MobileRequest extends FormRequest
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
            'area_code' => 'required',
            'mobile'    => 'required',
            'flag'      => 'required',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息
     */
    public function messages(): array
    {
        $required_msg = '入参错误：:attribute必填';

        return [
            'area_code.required' => $required_msg,
            'mobile.required'    => $required_msg,
            'flag.required'      => $required_msg,
        ];
    }

    /**
     * 获取验证错误的自定义属性
     */
    public function attributes(): array
    {
        return [
            'area_code' => '国际区号',
            'mobile'    => '手机号',
            'flag'      => '验证码标签',
        ];
    }

}
