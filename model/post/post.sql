create table post(
    id binary(16) not null,
    title varchar(255),
    content text,
    img varchar(200),
    insert_date timestamp default current_timestamp,
    delete_date datetime null,
    primary key (id)
);