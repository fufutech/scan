import request from "@/utils/request";

/**
 * 测试
 */
export function login(data) {
	return request.post({
		url: "/admin/cs/testView",
		data
	});
}