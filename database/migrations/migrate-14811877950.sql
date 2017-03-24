CREATE TABLE IF NOT EXISTS `{pre}connect_user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `connect_code` char(30)  NOT NULL COMMENT '登录插件名sns_qq，sns_wechat',
  `user_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '会员ID',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否管理员,0是会员 ,1是管理员',
  `open_id` char(64)  NOT NULL DEFAULT '' COMMENT '标识',
  `token_secret` char(64)  DEFAULT '',
  `token` char(64)  NOT NULL DEFAULT '' COMMENT 'token',
  `profile` text COMMENT '序列化用户信息',
  `create_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT '创建时间',
  `expires_in` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'token过期时间',
  `expires_at` int(10) unsigned NOT NULL DEFAULT '0' COMMENT 'token保存时间',
  PRIMARY KEY (`id`),
  KEY `open_id` (`connect_code`,`open_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;
