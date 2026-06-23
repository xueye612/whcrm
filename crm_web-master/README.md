
# CRM-11.0-web（PHP版）

```

注：CRM采用全新的前后端分离模式，本仓库代码中已集成前端vue打包后文件，可免去打包操作。如需调整前端代码，请单独下载前端代码


#### 项目目录
``` lua
├── build -- webpack 配置文件
├── config -- 项目配置文件
├── src -- 源码目录
│   ├── api -- axios请求接口
│   ├── assets -- 静态图片资源文件
│   ├── components -- 通用组件
│   ├── directives -- 通用指令
│   ├── filters -- 通用过滤器
│   ├── mixins -- 通用混入
│   ├── router -- vue-router路由配置
│   ├── store -- vuex状态管理
│   ├── styles -- 全局css样式
│   ├── utils -- 工具类
│   └── views -- 前端页面
│       ├── addressBook -- 通讯录
│       ├── admin -- 系统管理
│       ├── bi -- 商业智能
│       ├── calendar -- 日历
│       ├── crm -- 客户管理
│       ├── email -- 邮箱
│       ├── hrm -- 人力资源
│       ├── jxc -- 进销存
│       ├── knowledge -- 知识库
│       ├── layout -- 模块框架
│       ├── login -- 登录
│       ├── oa -- 办公
│       ├── pm -- 项目管理
│       ├── taskExamine -- 任务审批
│       ├── user -- 个人中心
│       └── workLog -- 工作日志
└── static -- 静态资源
```

#### 项目运行

- 下载node并安装(LTS)：https://nodejs.org/;
- 该项目为前后端分离项目，本地访问需搭建后台环境，
- 访问线上接口无需搭建后台环境，只需将config/index.js文件中的dev下的proxyTable中的target改为线上地址即可;
- 克隆源代码到本地，使用VSCode打开，并搜索”百度key“，将其替换为自己申请的。申请地址：
- 执行 npm install，下载相关依赖;
- 执行 npm run dev，运行项目;
- 执行成功，即可访问http://localhost:8090，查看悟空CRM;

#### 项目打包

- 需将config/prod.env.js文件中的BASE_API，调整为自己需要的
- 执行 npm run build
- 打包完成，将自动生成dist文件夹


#### 技术栈

| 技术 | 说明 | 版本 |
| --- | --- | --- |
| [Vue](https://vuejs.org) | 框架 | 2.5.17 |
| [Vue-router](https://router.vuejs.org) | 路由框架 | 3.0.1 |
| [Vuex](https://vuex.vuejs.org) | 全局状态管理框架 | 3.0.1 |
| [Element](https://element.eleme.io) | UI框架 | 2.12.0 |
| [Axios](https://github.com/axios/axios) | HTTP框架 | 0.18.0 |



