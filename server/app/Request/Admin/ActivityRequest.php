<?php

declare(strict_types=1);

namespace App\Request\Admin;

use Hyperf\Validation\Request\FormRequest;

class ActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected $scenes
        = [
            'activityCreate' => ['title', 'banner', 'lightspot', 'region',
                                 'level', 'location', 'destination', 'amount',
                                 'details', 'journey', 'notice', 'duration',
                                 'start_time', 'end_time', 'points',
                                 'people_less', 'people_more',
                                 'activity_people', 'uid', 'identity'],
        ];

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'title'           => 'required',
            'banner'          => 'required',
            'region'          => 'required',
            'level'           => 'required',
            'location'        => 'required',
            'destination'     => 'required',
            'amount'          => 'required',
            'details'         => 'required',
            'journey'         => 'required',
            'notice'          => 'required',
            'duration'        => 'required',
            'start_time'      => 'required',
            'end_time'        => 'required',
            'points'          => 'required',
            'people_less'     => 'required|integer',
            'people_more'     => 'required|integer',
            "activity_people" => 'required|array',
        ];
    }

    /**
     * 获取已定义验证规则的错误消息
     */
    public function messages(): array
    {
        $required_msg = ':attribute必填';

        return [
            'title.required'           => $required_msg,
            'banner.required'          => $required_msg,
            'lightspot'                => $required_msg,
            'region.required'          => $required_msg,
            'level.required'           => $required_msg,
            'location.required'        => $required_msg,
            'destination.required'     => $required_msg,
            'amount.required'          => $required_msg,
            'details.required'         => $required_msg,
            'journey.required'         => $required_msg,
            'notice.required'          => $required_msg,
            'duration.required'        => $required_msg,
            'start_time.required'      => $required_msg,
            'end_time.required'        => $required_msg,
            'points.required'          => $required_msg,
            'people_less.required'     => $required_msg,
            'people_more.required'     => $required_msg,
            'activity_people.required' => $required_msg,
        ];
    }

    /**
     * 获取验证错误的自定义属性
     */
    public function attributes(): array
    {
        return [
            'title'           => '活动标题',
            'banner'          => '轮播头图',
            'lightspot'       => '活动亮点',
            'region'          => '活动区域',
            'level'           => '活动难度',
            'location'        => '集合地点',
            'destination'     => '目的地',
            'amount'          => '报名金额',
            'details'         => '活动详情',
            'journey'         => '行程与准备',
            'notice'          => '费用须知',
            'duration'        => '活动时长',
            'start_time'      => '活动开始时间',
            'end_time'        => '活动结束时间',
            'points'          => '活动积分',
            'people_less'     => '活动成行人数',
            'people_more'     => '活动人数上限',
            'activity_people' => '活动领队',
        ];
    }

}


