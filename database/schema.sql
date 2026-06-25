create table if not exists companies (
  id int unsigned auto_increment primary key,
  name varchar(160) not null,
  document varchar(32) null,
  email varchar(160) null,
  phone varchar(40) null,
  address varchar(255) null,
  logo_url varchar(255) null,
  primary_color varchar(20) not null default '#15201d',
  secondary_color varchar(20) not null default '#b8664b',
  cover_image_url varchar(255) null,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists users (
  id int unsigned auto_increment primary key,
  company_id int unsigned null,
  name varchar(160) not null,
  email varchar(160) not null unique,
  password_hash varchar(255) not null,
  role enum('SUPER_ADMIN','ADMIN_EMPRESA','PROJETISTA','CONFERENTE') not null default 'ADMIN_EMPRESA',
  active tinyint(1) not null default 1,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  constraint users_company_fk foreign key (company_id) references companies(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists client_projects (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  designer_id int unsigned null,
  client_name varchar(160) not null,
  client_address varchar(255) null,
  client_phone varchar(40) not null,
  project_name varchar(160) not null,
  current_stage enum('PROJETO','NEGOCIACAO','CONFERENCIA','MONTAGEM','ASSISTENCIA','FINALIZADO') not null default 'PROJETO',
  project_status varchar(80) null,
  entry_date date null,
  presentation_date date null,
  negotiation_status varchar(80) null,
  new_proposal_value decimal(14,2) null,
  closed_value decimal(14,2) null,
  closing_date date null,
  conference_status varchar(80) null,
  sent_to_factory_date date null,
  billing_date date null,
  assembly_status varchar(80) null,
  assembly_started_date date null,
  assembly_finished_date date null,
  assistance_status varchar(80) null,
  assistance_date date null,
  order_date date null,
  finished_at datetime null,
  notes text null,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  constraint projects_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint projects_designer_fk foreign key (designer_id) references users(id) on delete set null,
  index projects_company_stage_idx (company_id, current_stage),
  index projects_designer_idx (designer_id)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists flow_history (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  client_project_id int unsigned not null,
  from_stage enum('PROJETO','NEGOCIACAO','CONFERENCIA','MONTAGEM','ASSISTENCIA','FINALIZADO') null,
  to_stage enum('PROJETO','NEGOCIACAO','CONFERENCIA','MONTAGEM','ASSISTENCIA','FINALIZADO') not null,
  action varchar(160) not null,
  user_id int unsigned null,
  notes text null,
  created_at timestamp not null default current_timestamp,
  constraint history_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint history_project_fk foreign key (client_project_id) references client_projects(id) on delete cascade,
  constraint history_user_fk foreign key (user_id) references users(id) on delete set null,
  index history_project_idx (client_project_id, created_at)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists financial_sales (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  client_project_id int unsigned null,
  designer_id int unsigned null,
  client_name varchar(160) not null,
  project_name varchar(160) not null,
  sold_value decimal(14,2) not null default 0,
  payment_method varchar(80) not null,
  sale_date date not null,
  notes text null,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  constraint sales_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint sales_project_fk foreign key (client_project_id) references client_projects(id) on delete set null,
  constraint sales_designer_fk foreign key (designer_id) references users(id) on delete set null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists financial_payments (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  financial_sale_id int unsigned not null,
  payment_number int not null,
  amount decimal(14,2) not null default 0,
  payment_date date not null,
  created_at timestamp not null default current_timestamp,
  constraint payments_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint payments_sale_fk foreign key (financial_sale_id) references financial_sales(id) on delete cascade,
  unique key payment_number_unique (financial_sale_id, payment_number)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists designer_goals (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  designer_id int unsigned not null,
  month tinyint unsigned not null,
  year smallint unsigned not null,
  goal_amount decimal(14,2) not null default 0,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  constraint goals_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint goals_designer_fk foreign key (designer_id) references users(id) on delete cascade,
  unique key goals_unique (company_id, designer_id, month, year)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists subscriptions (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null unique,
  plan enum('ESSENCIAL','PROFISSIONAL','PREMIUM') not null default 'PROFISSIONAL',
  status enum('TRIAL','ACTIVE','PAST_DUE','CANCELED','BLOCKED') not null default 'TRIAL',
  current_period_start date null,
  current_period_end date null,
  trial_ends_at date null,
  canceled_at datetime null,
  provider varchar(80) null,
  external_customer_id varchar(160) null,
  external_subscription_id varchar(160) null,
  checkout_url varchar(255) null,
  selected_plan_key varchar(40) null,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  constraint subscriptions_company_fk foreign key (company_id) references companies(id) on delete cascade
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists financial_commission_settings (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  designer_id int unsigned null,
  month tinyint unsigned not null,
  year smallint unsigned not null,
  commission_percent decimal(6,3) not null default 0,
  created_at timestamp not null default current_timestamp,
  updated_at timestamp null default null on update current_timestamp,
  constraint commission_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint commission_designer_fk foreign key (designer_id) references users(id) on delete cascade,
  unique key commission_company_month_unique (company_id, designer_id, month, year)
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists import_batches (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  type varchar(80) not null,
  file_name varchar(255) not null,
  status varchar(40) not null,
  total_rows int not null default 0,
  success_rows int not null default 0,
  error_rows int not null default 0,
  created_by_user_id int unsigned null,
  created_at timestamp not null default current_timestamp,
  constraint import_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint import_user_fk foreign key (created_by_user_id) references users(id) on delete set null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;

create table if not exists export_logs (
  id int unsigned auto_increment primary key,
  company_id int unsigned not null,
  type varchar(80) not null,
  format varchar(20) not null,
  filters json null,
  created_by_user_id int unsigned null,
  created_at timestamp not null default current_timestamp,
  constraint export_company_fk foreign key (company_id) references companies(id) on delete cascade,
  constraint export_user_fk foreign key (created_by_user_id) references users(id) on delete set null
) engine=InnoDB default charset=utf8mb4 collate=utf8mb4_unicode_ci;
