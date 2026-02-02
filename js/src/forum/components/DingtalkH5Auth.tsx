import app from 'flarum/forum/app';

export default class DingtalkH5Auth {
  static async autoLogin() {
    // 检查是否在钉钉环境中
    if (!/DingTalk/i.test(navigator.userAgent)) {
      return;
    }

    // 检查是否已登录
    if (app.session.user) {
      return;
    }

    // 检查是否启用H5登录
    if (!app.forum.attribute('dingtalkH5Enabled')) {
      return;
    }

    try {
      // 加载钉钉JSAPI
      await this.loadDingtalkJSAPI();

      const dd = (window as any).dd;
      const corpId = app.forum.attribute('dingtalkCorpId');

      if (!corpId) {
        console.warn('DingTalk corpId not configured');
        return;
      }

      dd.ready(() => {
        dd.runtime.permission.requestAuthCode({
          corpId: corpId,
          onSuccess: async (result: { code: string }) => {
            try {
              const response = await app.request({
                method: 'POST',
                url: app.forum.attribute('apiUrl') + '/dingtalk/h5-login',
                body: { code: result.code },
              });

              if (response.success) {
                window.location.reload();
              }
            } catch (error) {
              console.error('DingTalk H5 auto login failed:', error);
            }
          },
          onFail: (err: any) => {
            console.error('DingTalk H5 auth code request failed:', err);
          },
        });
      });

      dd.error((err: any) => {
        console.error('DingTalk JSAPI error:', err);
      });
    } catch (error) {
      console.error('DingTalk H5 auto login initialization failed:', error);
    }
  }

  static loadDingtalkJSAPI(): Promise<void> {
    return new Promise((resolve, reject) => {
      // 检查是否已加载
      if (typeof (window as any).dd !== 'undefined') {
        resolve();
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://g.alicdn.com/dingding/dingtalk-jsapi/3.0.12/dingtalk.open.js';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load DingTalk JSAPI'));
      document.head.appendChild(script);
    });
  }
}
