alter table client_projects
  add column intern_user_id int unsigned null after designer_id,
  add index projects_intern_idx (intern_user_id),
  add constraint projects_intern_fk foreign key (intern_user_id) references users(id) on delete set null;
