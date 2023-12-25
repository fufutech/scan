import request from "@/utils/request";

/**
 * 登录
 */
export function login(data) {
	return request.post({
		url: "/login/login",
		data
	});
}

/**
 * 列表
 */
export function batchList(data) {
	return request.post({
		url: "/scan/batchList",
		data
	});
}

/**
 * 创建批次
 */
export function batchCreate(data) {
	return request.post({
		url: "/scan/batchCreate",
		data
	});
}

/**
 * 创建批次
 */
export function batchInfo(data) {
	return request.post({
		url: "/scan/batchInfo",
		data
	});
}