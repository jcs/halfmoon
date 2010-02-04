-- CREATE USER test;
-- GRANT ALL PRIVILEGES ON test to test;

CREATE TABLE authors(
	author_id SERIAL PRIMARY KEY,
	parent_author_id INT,
	name VARCHAR(25) NOT NULL DEFAULT 'default_name',
	updated_at timestamp,
	created_at timestamp,
	some_date date,
	encrypted_password varchar(50),
	mixedCaseField varchar(50)
);

CREATE TABLE books(
	book_id SERIAL PRIMARY KEY,
	author_id INT,
	secondary_author_id INT,
	name VARCHAR(50),
	numeric_test VARCHAR(10) DEFAULT '0',
	special NUMERIC(10,2) DEFAULT 0
);

CREATE TABLE venues (
	id SERIAL PRIMARY KEY,
	name varchar(50),
	city varchar(60),
	state char(2),
	address varchar(50),
	phone varchar(10) default NULL,
	UNIQUE(name,address)
);

CREATE TABLE events (
	id SERIAL PRIMARY KEY,
	venue_id int NOT NULL,
	host_id int NOT NULL,
	title varchar(50) NOT NULL,
	description varchar(10),
	type varchar(15) default NULL
);

CREATE TABLE hosts(
	id SERIAL PRIMARY KEY,
	name VARCHAR(25)
);

CREATE TABLE employees (
	id SERIAL PRIMARY KEY,
	first_name VARCHAR(255) NOT NULL,
	last_name VARCHAR(255) NOT NULL,
	nick_name VARCHAR(255) NOT NULL
);

CREATE TABLE positions (
	id SERIAL PRIMARY KEY,
	employee_id int NOT NULL,
	title VARCHAR(255) NOT NULL,
	active SMALLINT NOT NULL
);

CREATE TABLE "rm-bldg"(
    "rm-id" SERIAL PRIMARY KEY,
    "rm-name" VARCHAR(10) NOT NULL,
    "space out" VARCHAR(1) NOT NULL
);

CREATE TABLE awesome_people(
	id serial primary key,
	author_id int,
	is_awesome int default 1
);
