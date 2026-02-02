import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import humanTime from 'flarum/common/helpers/humanTime';

interface DingtalkIndexCardAttrs {
  user: any;
}

export default class DingtalkIndexCard extends Component<DingtalkIndexCardAttrs> {
  loading: boolean = true;
  bound: boolean = false;
  dingtalkNickname: string = '';
  dingtalkAvatar: string = '';
  dingtalkMobile: string = '';
  dingtalkEmail: string = '';
  loginTime: Date | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadBindStatus();
  }

  async loadBindStatus() {
    this.loading = true;
    m.redraw();

    try {
      const response = await app.request({
        method: 'GET',
        url: app.forum.attribute('apiUrl') + '/dingtalk/bind-status',
      });

      this.bound = response.bound;
      this.dingtalkNickname = response.dingtalk_nickname || '';
      this.dingtalkAvatar = response.dingtalk_avatar || '';
      this.dingtalkMobile = response.dingtalk_mobile || '';
      this.dingtalkEmail = response.dingtalk_email || '';
      
      // 获取登录时间
      const user = this.attrs.user;
      if (user && user.lastSeenAt) {
        this.loginTime = user.lastSeenAt();
      }
    } catch (error) {
      console.error('Failed to load dingtalk bind status:', error);
      this.bound = false;
    } finally {
      this.loading = false;
      m.redraw();
    }
  }

  view() {
    if (this.loading) {
      return (
        <div className="DingtalkIndexCard">
          <div className="DingtalkIndexCard-loading">
            <LoadingIndicator size="small" />
          </div>
        </div>
      );
    }

    return (
      <div className="DingtalkIndexCard">
        <div className="DingtalkIndexCard-header">
          <span className="DingtalkIndexCard-title">
            {app.translator.trans('jiushutech-dingtalk-login.forum.user_card.title')}
          </span>
        </div>
        <div className="DingtalkIndexCard-content">
          {this.bound ? this.renderBoundContent() : this.renderUnboundContent()}
        </div>
      </div>
    );
  }

  renderBoundContent() {
    const user = this.attrs.user;
    
    return (
      <div className="DingtalkIndexCard-bound">
        <div className="DingtalkIndexCard-user">
          <div className="DingtalkIndexCard-avatar">
            {this.dingtalkAvatar ? (
              <img src={this.dingtalkAvatar} alt="" />
            ) : (
              <span className="Avatar" style={{ '--avatar-bg': '#007FFF' }}>
                {this.dingtalkNickname ? this.dingtalkNickname.charAt(0).toUpperCase() : '?'}
              </span>
            )}
          </div>
          <div className="DingtalkIndexCard-info">
            <div className="DingtalkIndexCard-nickname">
              {this.dingtalkNickname || app.translator.trans('jiushutech-dingtalk-login.forum.user_card.dingtalk_user')}
            </div>
            <div className="DingtalkIndexCard-status">
              <span className="DingtalkIndexCard-badge DingtalkIndexCard-badge--bound">
                <i className="fas fa-check-circle"></i>
                {app.translator.trans('jiushutech-dingtalk-login.forum.user_card.bound')}
              </span>
            </div>
          </div>
        </div>
        
        <ul className="DingtalkIndexCard-details">
          {/* 手机号和邮箱显示功能暂时隐藏，此版本不启用
          {this.dingtalkMobile && (
            <li>
              <i className="fas fa-phone icon"></i>
              <span>{this.maskMobile(this.dingtalkMobile)}</span>
            </li>
          )}
          {this.dingtalkEmail && (
            <li>
              <i className="fas fa-envelope icon"></i>
              <span>{this.maskEmail(this.dingtalkEmail)}</span>
            </li>
          )}
          */}
          {user && user.lastSeenAt && user.lastSeenAt() && (
            <li>
              <i className="fas fa-clock icon"></i>
              <span>
                {app.translator.trans('jiushutech-dingtalk-login.forum.user_card.online_since')}
                {humanTime(user.lastSeenAt())}
              </span>
            </li>
          )}
        </ul>
      </div>
    );
  }

  renderUnboundContent() {
    return (
      <div className="DingtalkIndexCard-unbound">
        <div className="DingtalkIndexCard-unboundMessage">
          <i className="fas fa-link"></i>
          <span>{app.translator.trans('jiushutech-dingtalk-login.forum.user_card.not_bound')}</span>
        </div>
        <Button
          className="Button Button--primary Button--block"
          onclick={this.handleBind.bind(this)}
        >
          {app.translator.trans('jiushutech-dingtalk-login.forum.user_card.bind_now')}
        </Button>
      </div>
    );
  }

  maskMobile(mobile: string): string {
    if (mobile.length >= 7) {
      return mobile.substring(0, 3) + '****' + mobile.substring(mobile.length - 4);
    }
    return mobile;
  }

  maskEmail(email: string): string {
    const atIndex = email.indexOf('@');
    if (atIndex > 2) {
      return email.substring(0, 2) + '***' + email.substring(atIndex);
    } else if (atIndex > 0) {
      return email.charAt(0) + '***' + email.substring(atIndex);
    }
    return email;
  }

  handleBind() {
    const isDingtalkEnv = /DingTalk/i.test(navigator.userAgent);
    
    if (isDingtalkEnv) {
      this.handleH5Bind();
    } else {
      this.handleQRCodeBind();
    }
  }

  handleQRCodeBind() {
    const width = 500;
    const height = 600;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    const authUrl = app.forum.attribute('baseUrl') + '/auth/dingtalk?bind=1';
    
    const popup = window.open(
      authUrl,
      'dingtalk_bind',
      `width=${width},height=${height},left=${left},top=${top},scrollbars=yes,resizable=yes`
    );

    const checkClosed = setInterval(() => {
      if (popup?.closed) {
        clearInterval(checkClosed);
        this.loadBindStatus();
      }
    }, 500);
  }

  async handleH5Bind() {
    try {
      if (typeof (window as any).dd === 'undefined') {
        await this.loadDingtalkJSAPI();
      }

      const dd = (window as any).dd;
      const corpId = app.forum.attribute('dingtalkCorpId');

      dd.ready(() => {
        dd.runtime.permission.requestAuthCode({
          corpId: corpId,
          onSuccess: async (result: { code: string }) => {
            await this.submitBindCode(result.code);
          },
          onFail: (err: any) => {
            app.alerts.show({ type: 'error' }, '获取授权码失败');
          },
        });
      });
    } catch (error: any) {
      app.alerts.show({ type: 'error' }, error.message || '初始化失败');
    }
  }

  async submitBindCode(code: string) {
    try {
      const response = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/dingtalk/bind',
        body: { code, type: 'h5' },
      });

      if (response.success) {
        app.alerts.show({ type: 'success' }, app.translator.trans('jiushutech-dingtalk-login.forum.settings.bind_success'));
        this.loadBindStatus();
      } else {
        app.alerts.show({ type: 'error' }, response.message || '绑定失败');
      }
    } catch (error: any) {
      app.alerts.show({ type: 'error' }, error.message || '绑定失败');
    }
  }

  loadDingtalkJSAPI(): Promise<void> {
    return new Promise((resolve, reject) => {
      const script = document.createElement('script');
      script.src = 'https://g.alicdn.com/dingding/dingtalk-jsapi/3.0.12/dingtalk.open.js';
      script.onload = () => resolve();
      script.onerror = () => reject(new Error('Failed to load DingTalk JSAPI'));
      document.head.appendChild(script);
    });
  }
}
