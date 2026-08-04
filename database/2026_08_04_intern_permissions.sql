alter table users
  modify role enum('SUPER_ADMIN','ADMIN_EMPRESA','PROJETISTA','CONFERENTE','ESTAGIARIO') not null default 'ADMIN_EMPRESA',
  add column supervisor_user_id int unsigned null after role,
  add column intern_data_scope enum('SUPERVISOR','COMPANY') not null default 'SUPERVISOR' after supervisor_user_id,
  add index users_supervisor_idx (supervisor_user_id),
  add constraint users_supervisor_fk foreign key (supervisor_user_id) references users(id) on delete set null;

create table if not exists intern_permissions (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  intern_user_id int unsigned not null,
  tab_key varchar(50) not null,
  access_level enum('VIEW','EDIT','DELETE') not null,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  unique key intern_permission_unique (intern_user_id, tab_key),
  index intern_permission_company_idx (company_id),
  constraint intern_permission_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint intern_permission_user_fk foreign key (intern_user_id) references users(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

