import app from 'flarum/forum/app';

export default class DingtalkH5Auth {
  static async autoLogin() {
    console.log('[DingTalk H5Auth] 开始自动登录检测...');
    
    // 检查是否在钉钉环境中
    if (!/DingTalk/i.test(navigator.userAgent)) {
      console.log('[DingTalk H5Auth] 非钉钉环境，跳过自动登录');
      return;
    }

    // 检查是否已登录
    if (app.session.user) {
      console.log('[DingTalk H5Auth] 用户已登录，跳过自动登录');
      return;
    }

    // 检查是否启用H5登录 - 在钉钉环境中默认启用
    const h5Enabled = app.forum.attribute('dingtalkH5Enabled');
    console.log('[DingTalk H5Auth] H5登录启用状态:', h5Enabled);
    
    if (h5Enabled === false) {
      console.log('[DingTalk H5Auth] H5登录未启用，跳过自动登录');
      return;
    }

    try {
      // 加载钉钉JSAPI
      console.log('[DingTalk H5Auth] 加载钉钉JSAPI...');
      await this.loadDingtalkJSAPI();

      const dd = (window as any).dd;
      const corpId = app.forum.attribute('dingtalkCorpId');

      console.log('[DingTalk H5Auth] CorpId:', corpId);

      if (!corpId) {
        console.warn('[DingTalk H5Auth] CorpId未配置，跳过自动登录');
        return;
      }

      dd.ready(() => {
        console.log('[DingTalk H5Auth] 钉钉JSAPI就绪，请求授权码...');
        dd.runtime.permission.requestAuthCode({
          corpId: corpId,
          onSuccess: async (result: { code: string }) => {
            console.log('[DingTalk H5Auth] 获取授权码成功');
            try {
              const response = await app.request({
                method: 'POST',
                url: app.forum.attribute('apiUrl') + '/dingtalk/h5-login',
                body: { code: result.code },
                errorHandler: () => {}, // 禁用默认错误处理
              });

              if (response.success) {
                console.log('[DingTalk H5Auth] 登录成功，刷新页面');
                window.location.reload();
              } else {
                console.error('[DingTalk H5Auth] 登录失败:', response.message);
                // 显示弹窗错误提示
                this.showErrorAlert(response.message || app.translator.trans('jiushutech-dingtalk-login.forum.errors.auth_failed'));
              }
            } catch (error: any) {
              console.error('[DingTalk H5Auth] 登录请求失败:', error);
              // 解析错误消息
              let errorMessage = app.translator.trans('jiushutech-dingtalk-login.forum.errors.auth_failed');
              if (error.responseJSON && error.responseJSON.message) {
                errorMessage = error.responseJSON.message;
              } else if (error.response && error.response.message) {
                errorMessage = error.response.message;
              } else if (error.message) {
                errorMessage = error.message;
              }
              // 显示弹窗错误提示
              this.showErrorAlert(errorMessage);
            }
          },
          onFail: (err: any) => {
            console.error('[DingTalk H5Auth] 获取授权码失败:', err);
            this.showErrorAlert(app.translator.trans('jiushutech-dingtalk-login.forum.errors.auth_failed') + ': ' + (err.message || JSON.stringify(err)));
          },
        });
      });

      dd.error((err: any) => {
        console.error('[DingTalk H5Auth] JSAPI错误:', err);
        this.showErrorAlert(app.translator.trans('jiushutech-dingtalk-login.forum.errors.auth_failed') + ': ' + (err.message || JSON.stringify(err)));
      });
    } catch (error: any) {
      console.error('[DingTalk H5Auth] 初始化失败:', error);
      this.showErrorAlert(error.message || app.translator.trans('jiushutech-dingtalk-login.forum.errors.auth_failed'));
    }
  }

  /**
   * 显示错误弹窗提示
   */
  static showErrorAlert(message: string) {
    // 使用 Flarum 的 alerts 系统显示错误
    app.alerts.show(
      { type: 'error' },
      message
    );
  }

  static loadDingtalkJSAPI(): Promise<void> {
    return new Promise((resolve, reject) => {
      // 检查是否已加载
      if (typeof (window as any).dd !== 'undefined') {
        console.log('[DingTalk H5Auth] JSAPI已加载');
        resolve();
        return;
      }

      console.log('[DingTalk H5Auth] 动态加载JSAPI脚本...');
      const script = document.createElement('script');
      script.src = 'https://g.alicdn.com/dingding/dingtalk-jsapi/3.0.12/dingtalk.open.js';
      script.onload = () => {
        console.log('[DingTalk H5Auth] JSAPI脚本加载完成');
        resolve();
      };
      script.onerror = () => {
        console.error('[DingTalk H5Auth] JSAPI脚本加载失败');
        reject(new Error('Failed to load DingTalk JSAPI'));
      };
      document.head.appendChild(script);
    });
  }
}
