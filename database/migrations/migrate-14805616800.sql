ALTER TABLE `{pre}comment` ADD `like_num` INT(10) NOT NULL DEFAULT '0' COMMENT '点赞数';
ALTER TABLE `{pre}comment` ADD `dis_browse_num` INT(10) UNSIGNED NOT NULL DEFAULT '0' COMMENT '浏览数';