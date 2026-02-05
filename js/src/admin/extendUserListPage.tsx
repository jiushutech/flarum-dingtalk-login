import app from 'flarum/admin/app';
import { extend } from 'flarum/common/extend';
import UserListPage from 'flarum/admin/components/UserListPage';
import type ItemList from 'flarum/common/utils/ItemList';

interface DingtalkBindStats {
  totalUsers: number;
  boundUsers: number;
  percentage: number;
}

let dingtalkStats: DingtalkBindStats | null = null;
let statsLoading = false;
let statsInserted = false;

/**
 * 检查是否启用了显示绑定统计功能
 */
function isBindStatsEnabled(): boolean {
  const setting = app.data.settings['jiushutech-dingtalk-login.show_bind_stats'];
  // 默认为启用（当设置不存在或为'1'时）
  return setting !== '0';
}

/**
 * 加载钉钉绑定统计数据
 */
async function loadDingtalkStats(): Promise<DingtalkBindStats | null> {
  if (statsLoading) return dingtalkStats;
  
  statsLoading = true;
  try {
    const response = await app.request<{ data: DingtalkBindStats }>({
      method: 'GET',
      url: app.forum.attribute('apiUrl') + '/dingtalk/bind-stats',
    });
    dingtalkStats = response.data;
    insertStatsToToolbar();
    return dingtalkStats;
  } catch (e) {
    console.error('Failed to load dingtalk stats:', e);
    return null;
  } finally {
    statsLoading = false;
  }
}

/**
 * 将统计组件插入到工具栏区域（表格上方右侧）
 */
function insertStatsToToolbar() {
  if (!dingtalkStats || statsInserted || !isBindStatsEnabled()) return;
  
  // 查找用户列表的actions区域或表格容器
  const userListGrid = document.querySelector('.UserListPage-grid');
  if (!userListGrid) return;
  
  // 检查是否已经插入
  if (document.querySelector('.DingtalkBindStats')) {
    statsInserted = true;
    return;
  }
  
  // 查找表格头部行
  const gridHeader = userListGrid.querySelector('.UserListPage-grid-header');
  if (!gridHeader) return;
  
  // 创建统计组件
  const statsDiv = document.createElement('div');
  statsDiv.className = 'DingtalkBindStats';
  statsDiv.innerHTML = `
    <span class="DingtalkBindStats-icon"><i class="fas fa-comment-dots"></i></span>
    <span class="DingtalkBindStats-label">${app.translator.trans('jiushutech-dingtalk-login.admin.users.bind_progress')}</span>
    <span class="DingtalkBindStats-value">${dingtalkStats.boundUsers}/${dingtalkStats.totalUsers}</span>
    <span class="DingtalkBindStats-percentage">${dingtalkStats.percentage}%</span>
  `;
  
  // 在表格前插入统计组件
  userListGrid.parentNode?.insertBefore(statsDiv, userListGrid);
  statsInserted = true;
}

export default function extendUserListPage() {
  // 在页面创建后加载统计数据并插入DOM
  extend(UserListPage.prototype, 'oncreate', function (this: UserListPage) {
    statsInserted = false; // 重置状态
    
    if (!isBindStatsEnabled()) return;
    
    if (!dingtalkStats && !statsLoading) {
      loadDingtalkStats();
    } else if (dingtalkStats) {
      // 延迟执行以确保DOM已渲染
      setTimeout(() => insertStatsToToolbar(), 100);
    }
  });

  // 页面更新时也尝试插入
  extend(UserListPage.prototype, 'onupdate', function (this: UserListPage) {
    if (!isBindStatsEnabled()) return;
    
    if (dingtalkStats && !statsInserted) {
      insertStatsToToolbar();
    }
  });

  // 扩展用户列表的列定义，添加钉钉绑定状态列
  extend(UserListPage.prototype, 'columns', function (this: UserListPage, items: ItemList<any>) {
    // 检查是否启用了显示绑定统计功能
    if (!isBindStatsEnabled()) return;
    
    items.add(
      'dingtalkBound',
      {
        name: app.translator.trans('jiushutech-dingtalk-login.admin.users.dingtalk_column'),
        content: (user: any) => {
          const isBound = user.attribute('dingtalkBound');
          if (isBound) {
            return (
              <span className="DingtalkBoundIcon DingtalkBoundIcon--bound" title={String(app.translator.trans('jiushutech-dingtalk-login.admin.users.bound'))}>
                <svg className="DingtalkLogo" viewBox="0 0 1024 1024" width="20" height="20">
                  <path fill="#3296FA" d="M512 2C230.2 2 2 230.2 2 512s228.2 510 510 510 510-228.2 510-510S793.3 2 512 2z m235.9 442c-1 4.6-3.6 10.8-7.2 19.1l-0.5 0.5c-21.6 45.8-77.3 135.5-77.3 135.5l-0.5-0.5-16.5 28.3h78.8L574.3 826.8l34-136h-61.8l21.6-90.2c-17.5 4.1-38.1 9.8-62.3 18 0 0-33 19.1-94.8-37.1 0 0-41.7-37.1-17.5-45.8 10.3-4.1 50-8.8 81.4-12.9 42.2-5.7 68.5-8.8 68.5-8.8s-130.3 2.1-161.2-3.1c-30.9-4.6-70.1-56.7-78.3-102 0 0-12.9-24.7 27.8-12.9 40.2 11.8 209.2 45.8 209.2 45.8S321.4 375 307 358.5c-14.4-16.5-42.8-89.6-39.2-134.5 0 0 1.5-11.3 12.9-8.2 0 0 161.8 74.2 272.5 114.4C664.5 371.4 760.8 392 747.9 444z"/>
                </svg>
              </span>
            );
          }
          return (
            <span className="DingtalkBoundIcon DingtalkBoundIcon--unbound">
              {/* 未绑定不显示任何内容 */}
            </span>
          );
        },
      },
      45 // 在邮箱列后面
    );
  });
}
