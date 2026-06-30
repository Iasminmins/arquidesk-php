create table if not exists project_files (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  client_project_id int unsigned not null,
  uploaded_by_user_id int unsigned null,
  original_name varchar(255) not null,
  stored_name varchar(255) not null,
  file_size int unsigned not null default 0,
  mime_type varchar(100) not null default '',
  category varchar(40) not null default 'GERAL',
  created_at timestamp not null default current_timestamp,
  constraint pf_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint pf_project_fk foreign key (client_project_id) references client_projects(id) on delete cascade,
  constraint pf_user_fk foreign key (uploaded_by_user_id) references users(id) on delete set null,
  index pf_project_idx (client_project_id, company_id)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
