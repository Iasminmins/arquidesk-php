create table if not exists user_nav_preferences (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  user_id int unsigned not null,
  nav_key varchar(190) not null,
  visible tinyint(1) not null default 1,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  unique key user_nav_preference_unique (user_id, nav_key),
  index user_nav_preference_company_idx (company_id),
  constraint user_nav_preference_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint user_nav_preference_user_fk foreign key (user_id) references users(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
