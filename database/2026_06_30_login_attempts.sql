create table if not exists login_attempts (
  id int unsigned auto_increment primary key,
  ip varchar(45) not null,
  email varchar(160) not null default '',
  attempted_at timestamp not null default current_timestamp,
  index la_ip_time_idx (ip, attempted_at)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
