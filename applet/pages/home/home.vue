<template>
	<view>
		<uni-section title="新增盘点" type="line">
			<view class="example-body">
				<button @click="batchCreatePopup">新增盘点</button>
				<uni-popup ref="popup">
					<view class="top-form">
						<uni-forms :modelValue="batchData">
							<uni-forms-item label="批次:" name="name">
								<uni-easyinput type="text" v-model="batchData.name" placeholder="请输入盘点批次" />
							</uni-forms-item>

						</uni-forms>
						<button @click="batchCreateSubmit">确认新增</button>
					</view>
				</uni-popup>

			</view>
		</uni-section>


		<uni-section title="历史盘点" type="line">
			<view class="example-body">
				<uni-row v-for="(item,index) in list" :key="index" class="demo-uni-row">
					<uni-col :span="12">
						<text class="button-text">{{item.name}}</text>
					</uni-col>
					<uni-col :span="3">
						<text class="button-text">{{item.count}}件</text>
					</uni-col>
					<uni-col :span="3">
						<view class="tag-view">
							<uni-tag text="录入" @click="luru" type="primary" />
						</view>
					</uni-col>
					<uni-col :span="3">
						<view class="tag-view">
							<uni-tag @click="batchInfoPopup" text="详情" type="primary" />
							<!-- 				<uni-popup ref="popup" type="bottom">
								1231
							</uni-popup> -->

						</view>
					</uni-col>
					<uni-col :span="3">
						<view class="tag-view">
							<uni-tag text="导出" type="primary" />
						</view>
					</uni-col>
				</uni-row>
			</view>
		</uni-section>
	</view>
</template>
<script>
	import {
		batchList,
		batchCreate,
		batchInfo
	} from '@/utils/api.js'
	export default {
		components: {},
		data() {
			return {
				list: [],
				batchData: {
					"name": ""
				}
			}
		},
		onLoad() {
			this.getList()
		},
		methods: {
			getList() {
				batchList({}).then(res => {
					this.list = res.data.data
				})
			},
			batchCreateSubmit() {
				batchCreate(this.batchData).then(res => {
					if (res.data.code == 200) {
						uni.showToast({
							icon: 'none',
							title: res.data.msg
						});
						setTimeout(() => {
							this.$refs.popup.close('center')
							this.batchData = {
								"name": "",
							}
							this.getList()
						}, 1500)
					} else {
						uni.showToast({
							icon: 'none',
							title: res.data.msg
						});
					}

				})
			},
			luru() {
				// 调起条码扫描
				uni.scanCode({
					scanType: ['barCode'],
					success: function(res) {
						console.log('条码类型：' + res.scanType);
						console.log('条码内容：' + res.result);
					}
				});
			},
			batchCreatePopup() {
				// 通过组件定义的ref调用uni-popup方法 ,如果传入参数 ，type 属性将失效 ，仅支持 ['top','left','bottom','right','center']
				this.$refs.popup.open('center')
			},
			// batchInfoPopup() {
			// 	// 通过组件定义的ref调用uni-popup方法 ,如果传入参数 ，type 属性将失效 ，仅支持 ['top','left','bottom','right','center']
			// 	this.$refs.popup.open('center')
			// }
		}
	}
</script>
<style lang="scss">
	.demo-uni-row {
		padding: 20rpx;
		border-bottom: 1rpx solid #bcd0ea;
	}

	.demo-uni-col {
		height: 36px;
		border-radius: 5px;
	}

	.dark_deep {
		background-color: #99a9bf;
	}

	.dark {
		background-color: #d3dce6;
	}

	.light {
		background-color: #e5e9f2;
	}

	.example-body {
		/* #ifndef APP-NVUE */
		display: block;
		/* #endif */
		padding: 5rpx 10rpx 0;
		overflow: hidden;
	}

	.top-form {
		background-color: #dcdcdc;
		padding: 20rpx;
		border-radius: 20rpx;
	}
</style>