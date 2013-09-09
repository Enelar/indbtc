--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

--
-- Name: finances; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA finances;


ALTER SCHEMA finances OWNER TO postgres;

--
-- Name: matrix; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA matrix;


ALTER SCHEMA matrix OWNER TO postgres;

--
-- Name: users; Type: SCHEMA; Schema: -; Owner: postgres
--

CREATE SCHEMA users;


ALTER SCHEMA users OWNER TO postgres;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner: 
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner: 
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET search_path = finances, pg_catalog;

--
-- Name: bitcoin; Type: DOMAIN; Schema: finances; Owner: postgres
--

CREATE DOMAIN bitcoin AS numeric(15,10);


ALTER DOMAIN finances.bitcoin OWNER TO postgres;

SET search_path = matrix, pg_catalog;

--
-- Name: add_to_system(bigint, smallint, inet); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION add_to_system(_uid bigint, _level smallint, _ip inet) RETURNS bigint
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _i int8;
    _ret int8;
    _nid int8;
    _friend int8;
  BEGIN
  RAISE NOTICE 'add_to_system(%,%,%)', _uid, _level, _ip; 
    FOR _i IN SELECT users.get_line_parents(_uid)
    LOOP
      SELECT INTO _nid id FROM matrix.nodes WHERE uid=_i AND level=_level ORDER BY id DESC LIMIT 1;
      IF _nid IS NULL
      THEN
        CONTINUE;
      END IF;
      SELECT INTO _ret matrix.has_place(id, 2::int2) FROM matrix.nodes WHERE id=_nid;
      IF _ret IS NULL
      THEN
        CONTINUE;
      END IF;
      RAISE NOTICE 'Adding at the first attempt to %', _nid;
      INSERT INTO matrix.nodes(uid, parent, ip, level) VALUES(_uid, _ret, _ip, _level) RETURNING INTO _ret id;
      RETURN _ret;
    END LOOP;

    RAISE NOTICE 'Adding to friend failed';
--    SELECT INTO _friend inviter FROM users.referals WHERE invited=_uid;
--    IF _friend IS NOT NULL
--    THEN
--      _nid = matrix.search_refer_place(_friend, _level, int2(5));
--    ELSE
--      _nid = NULL;
--      RAISE NOTICE 'Friend is null! Inviter not found %', _uid;
--    END IF;
    _nid = matrix.search_refer_place(_uid, _level, int2(5));

    INSERT INTO matrix.nodes(uid, parent, ip, level) VALUES (_uid, _nid, _ip, _level) RETURNING INTO _ret id;

    RETURN _ret;
  END;
$$;


ALTER FUNCTION matrix.add_to_system(_uid bigint, _level smallint, _ip inet) OWNER TO postgres;

--
-- Name: append_parent(); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION append_parent() RETURNS trigger
    LANGUAGE plpgsql STRICT
    AS $$
  DECLARE
    parent matrix.nodes%ROWTYPE;
    result_id int4;
  BEGIN
    IF TG_OP = 'UPDATE'
    THEN
     --- IF OLD.parent != NEW.parent
      IF OLD.parent IS DISTINCT FROM NEW.parent
      THEN
        RAISE EXCEPTION 'Node cant change parents %, %', OLD.parent, NEW.parent;
      ELSE
        RETURN NEW; -- cause it update without our fate
      END IF;
    END IF;
    
    IF NEW.parent IS NULL
    THEN
      RETURN NEW;
    END IF;
 
    SELECT INTO parent * FROM matrix.nodes WHERE id=NEW.parent;
    
    IF parent IS NULL
    THEN
      RAISE EXCEPTION 'Parent not found';
    END IF;

    IF parent.childs IS NULL
    THEN
      UPDATE matrix.nodes SET childs = ARRAY[NEW.id] WHERE id=parent.id RETURNING INTO result_id id;
      IF result_id IS NOT NULL
      THEN
        RETURN NEW;
      END IF;
      RAISE EXCEPTION 'Parent wont be updated';
    END IF;
    
    IF parent.childs[2] IS NOT NULL
    THEN
      RAISE EXCEPTION 'Parent full of childs';
    END IF;
    
    UPDATE matrix.nodes SET childs[2] = NEW.id WHERE id=parent.id RETURNING INTO result_id id;
    
    IF result_id IS NOT NULL
    THEN
      RAISE NOTICE 'Success';
      RETURN NEW;
    END IF;
    
    RAISE EXCEPTION 'Parent wont be updated';
  END;  
$$;


ALTER FUNCTION matrix.append_parent() OWNER TO postgres;

--
-- Name: commited_childs(); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION commited_childs() RETURNS trigger
    LANGUAGE plpgsql STRICT
    AS $$
  DECLARE
   _parent matrix.nodes%ROWTYPE;
  BEGIN
    IF NEW.childs IS NOT NULL
    THEN
      IF NEW.commited = false
      THEN
        RAISE EXCEPTION 'Uncommited nodes cannot have childs %', NEW.id;
      END IF;
    END IF;

    LOCK matrix.nodes IN EXCLUSIVE MODE;
    IF NEW.parent IS NOT NULL
    THEN
      SELECT INTO _parent * FROM matrix.nodes WHERE id=NEW.parent;
      IF _parent.commited = false
      THEN
        RAISE EXCEPTION 'Uncommited parents cannot have childs %', _parent.id;
      END IF;
    END IF;
    RETURN NEW;
  END;  
$$;


ALTER FUNCTION matrix.commited_childs() OWNER TO postgres;

--
-- Name: commited_status_const(); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION commited_status_const() RETURNS trigger
    LANGUAGE plpgsql STRICT
    AS $$
  DECLARE
  BEGIN
    IF OLD.commited = false
    THEN
      RETURN NEW;
    END IF;
    IF NEW.commited = false
    THEN
      RAISE EXCEPTION 'Nodes cannot be uncommited';
    ELSE
      RETURN NEW;
    END IF;
  END;  
$$;


ALTER FUNCTION matrix.commited_status_const() OWNER TO postgres;

--
-- Name: count_childs(bigint[]); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION count_childs(bigint[]) RETURNS smallint
    LANGUAGE plpgsql
    AS $_$
  BEGIN
    IF $1 IS NULL
    THEN
      RETURN 0;
    END IF;
    
    IF $1[2] IS NULL
    THEN
      RETURN 1;
    END IF;
    
    RETURN 2;
  END;  
$_$;


ALTER FUNCTION matrix.count_childs(bigint[]) OWNER TO postgres;

--
-- Name: nods_id_seq; Type: SEQUENCE; Schema: matrix; Owner: postgres
--

CREATE SEQUENCE nods_id_seq
    START WITH 71
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE matrix.nods_id_seq OWNER TO postgres;

SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: nodes; Type: TABLE; Schema: matrix; Owner: postgres; Tablespace: 
--

CREATE TABLE nodes (
    id bigint DEFAULT nextval('nods_id_seq'::regclass) NOT NULL,
    uid bigint NOT NULL,
    parent bigint,
    childs bigint[],
    ip inet NOT NULL,
    snap timestamp without time zone DEFAULT now() NOT NULL,
    level smallint DEFAULT 0 NOT NULL,
    commited boolean DEFAULT false NOT NULL
);


ALTER TABLE matrix.nodes OWNER TO postgres;

--
-- Name: get_all_cycles(integer); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION get_all_cycles(_uid integer) RETURNS SETOF nodes
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _level int2;
  BEGIN
    FOR _level IN 0..7
    LOOP
      RETURN QUERY SELECT * FROM matrix.get_cycles(int4(_uid), int2(_level));
    END LOOP;   
  END;
$$;


ALTER FUNCTION matrix.get_all_cycles(_uid integer) OWNER TO postgres;

--
-- Name: get_childs(bigint, integer); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION get_childs(node bigint, depth integer) RETURNS SETOF bigint
    LANGUAGE plpgsql
    AS $_$
  DECLARE
    _count ALIAS FOR $2;
    _id ALIAS FOR $1;
    _childs int8[];
  BEGIN
    IF _count < 1
    THEN
      RETURN;
    END IF;

    SELECT INTO _childs childs FROM matrix.nodes WHERE id = _id;

    RETURN NEXT _childs[1];
    RETURN NEXT _childs[2];

    RETURN QUERY SELECT * FROM matrix.get_childs(_childs[1], _count - 1);
    RETURN QUERY SELECT * FROM matrix.get_childs(_childs[2], _count - 1);
  END;
$_$;


ALTER FUNCTION matrix.get_childs(node bigint, depth integer) OWNER TO postgres;

--
-- Name: get_cycles(integer, smallint); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION get_cycles(_uid integer, _level smallint) RETURNS SETOF nodes
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _nid int4;
    _childs int4[];
  BEGIN
    FOR _nid IN SELECT * FROM matrix.nodes WHERE uid=_uid AND level=_level
    LOOP
      WITH cycle_childs AS
      (
        SELECT * FROM matrix.get_childs(_nid, 2)
      ) SELECT INTO _childs array(SELECT get_childs FROM cycle_childs);
      RETURN QUERY SELECT * FROM matrix.nodes WHERE id=_nid; -- return root
      RETURN QUERY SELECT * FROM matrix.nodes WHERE id = ANY (_childs);
    END LOOP;
    
  END;
$$;


ALTER FUNCTION matrix.get_cycles(_uid integer, _level smallint) OWNER TO postgres;

--
-- Name: get_parents(bigint, integer); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION get_parents(bigint, integer) RETURNS SETOF bigint
    LANGUAGE plpgsql
    AS $_$
  DECLARE
    _count ALIAS FOR $2;
    _id ALIAS FOR $1;
    _parent int8;
  BEGIN
    IF _count < 1
    THEN
      RETURN;
    END IF;
    
    IF _id IS NOT NULL
    THEN
      SELECT INTO _parent parent FROM matrix.nodes WHERE id = _id;
    ELSE
      _parent = NULL;
    END IF;
    
    RETURN NEXT _parent;
    RETURN QUERY SELECT * FROM matrix.get_parents(_parent, _count - 1);
  END;
$_$;


ALTER FUNCTION matrix.get_parents(bigint, integer) OWNER TO postgres;

--
-- Name: has_place(bigint, smallint); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION has_place(_nid bigint, _depth smallint) RETURNS bigint
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _ret int8;
    _childs int8[];
    _i int8;
    _count_childs int2;
  BEGIN
    IF _nid IS NULL
    THEN
      RETURN NULL;
    END IF;
    
    IF _depth < 1
    THEN
      RETURN NULL;
    END IF;
    
    IF (SELECT commited FROM matrix.nodes WHERE id=_nid) = false
    THEN
      RAISE NOTICE 'Node didnt commited';
      RETURN NULL;
    END IF;
    
    _childs = array_alloc(2);

    SELECT INTO _count_childs matrix.count_childs(childs) FROM matrix.nodes WHERE id=_nid;
    
    IF _count_childs IS NULL
    THEN
      RAISE EXCEPTION 'FATALWTF matrix.has_place %', _nid;
    END IF;
    
    RAISE NOTICE 'matrix.has_place(%,%) count %, childs %', _nid, _depth, _count_childs, _childs;
    
    IF _count_childs != 2
    THEN
      RETURN _nid;
    END IF;
    
    IF _depth = 1::int2
    THEN
      RETURN NULL;
    END IF;
    
    -- _childs == 2 AND _depth > 1


    FOR _i IN SELECT unnest(childs) FROM matrix.nodes WHERE id=_nid
    LOOP
      _ret = matrix.has_place(_i, int2(_depth - 1));
      IF _ret IS NOT NULL
      THEN
        RETURN _ret;
      END IF;
    END LOOP;

    RAISE NOTICE 'matrix.has_place(%,%) matrix full', _nid, _depth;
    RETURN NULL;
  END;
$$;


ALTER FUNCTION matrix.has_place(_nid bigint, _depth smallint) OWNER TO postgres;

--
-- Name: is_completed(bigint, integer); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION is_completed(node bigint, depth integer) RETURNS boolean
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _child int8;
    _status bool;
    _count int4;
    _i int4;
  BEGIN
    _count = 2 ^ (depth + 1) - 1;
    _count = _count - 1;
    _i = 0;
    FOR _child IN SELECT * FROM matrix.get_childs(node, depth)
    LOOP
      SELECT INTO _status commited, _child id FROM matrix.nodes WHERE id=_child;
      IF _child IS NULL
      THEN
        RETURN FALSE;
      END IF;
      IF _status = FALSE
      THEN
        RETURN FALSE;
      END IF;
      _i = _i + 1;
    END LOOP;

    IF _i != _count
    THEN
      RETURN FALSE; -- child too few
    END IF;
    RETURN TRUE;
  END;
$$;


ALTER FUNCTION matrix.is_completed(node bigint, depth integer) OWNER TO postgres;

--
-- Name: level_count_constrait(); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION level_count_constrait() RETURNS trigger
    LANGUAGE plpgsql STRICT
    AS $$
  DECLARE
    _max_per_level int4 = 1;
    _count int4;
  BEGIN
    IF NEW.commited = true
    THEN
      RETURN NEW;
    END IF;

    SELECT INTO _count count(*) FROM matrix.nodes WHERE uid=NEW.uid AND level=NEW.level AND NOT matrix.is_completed(id, 2);
    IF _count >= _max_per_level
    THEN
      RAISE EXCEPTION 'Cant create more than one level. Pre';
    END IF;
    
    LOCK matrix.nodes IN ACCESS EXCLUSIVE MODE;
    SELECT INTO _count count(*) FROM matrix.nodes WHERE uid=NEW.uid AND level=NEW.level AND NOT matrix.is_completed(id, 2);
    IF _count < _max_per_level
    THEN
      RETURN NEW;
    ELSE
      RAISE EXCEPTION 'Cant create more than one level. Excluse';    
    END IF;
  END;  
$$;


ALTER FUNCTION matrix.level_count_constrait() OWNER TO postgres;

--
-- Name: remove_parent(); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION remove_parent() RETURNS trigger
    LANGUAGE plpgsql STRICT
    AS $$
  DECLARE
    parent matrix.nodes%ROWTYPE;
    result_id int4;
  BEGIN
    IF OLD.childs IS NOT NULL
    THEN  
      RAISE EXCEPTION 'Node cant be deleted while having childs';
    END IF;
    
    IF OLD.parent IS NULL
    THEN
      RAISE NOTICE 'Removing parent while it didnt exist already';
      RETURN OLD;
    END IF;
    
    SELECT INTO parent * FROM matrix.nodes WHERE id=OLD.parent;
    
    IF parent IS NULL
    THEN
      RAISE EXCEPTION 'Parent not found';
    END IF;
 
    IF parent.childs IS NULL
    THEN
      RAISE EXCEPTION 'Parent has zero childs!!!';
    END IF;
    
    IF parent.childs[2] = OLD.id
    THEN
      UPDATE matrix.nodes SET childs = ARRAY[childs[1]] WHERE id=parent.id RETURNING INTO result_id id;
      IF result_id IS NOT NULL
      THEN
		RAISE NOTICE 'matrix.remove_parent jkjflkj';      
        RETURN OLD;
      END IF;
      RAISE EXCEPTION 'Parent wont be updated';
    END IF;
    
    IF parent.childs[1] != OLD.id
    THEN
      RAISE EXCEPTION 'Child not found';
    END IF;
    
    IF parent.childs[2] IS NULL
    THEN
      UPDATE matrix.nodes SET childs = NULL WHERE id=parent.id RETURNING INTO result_id id;
      
      IF result_id IS NOT NULL
      THEN
		RAISE NOTICE 'matrix.remove_parent result id is null 116';  
        RETURN OLD;
      END IF;
      
      RAISE EXCEPTION 'Parent wont be updated';
    END IF;
    
    UPDATE matrix.nodes SET childs = ARRAY[childs[2]] WHERE id=parent.id RETURNING INTO result_id id;
    
    IF result_id IS NOT NULL
    THEN
	  RAISE NOTICE 'matrix.remove_parent result id is null 127';      
      RETURN OLD;
    END IF;
    
    RAISE EXCEPTION 'Parent wont be updated';
  END;  
$$;


ALTER FUNCTION matrix.remove_parent() OWNER TO postgres;

--
-- Name: search_refer_place(bigint, integer); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION search_refer_place(_uid bigint, _level integer) RETURNS bigint
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _referals int8[];
    _i int8;
    _nid int8;
    _ret int8;
  BEGIN
    IF _level < 1
    THEN
      RETURN NULL;
    END IF;
    
    SELECT INTO _referals ARRAY(SELECT invited FROM users.referals WHERE inviter=_uid);
    FOR _i IN SELECT _referals
    LOOP
      SELECT INTO _nid id FROM matrix.nodes WHERE uid=_i ORDER BY time DESC LIMIT 1;
      _ret = matrix.has_place(_nid);
      IF _ret IS NOT NULL
      THEN
        RETURN _ret;
      END IF;
    END LOOP;

    FOR _i IN SELECT _referals
    LOOP
      _ret = matrix.search_refer_place(_i, _level - 1);
      IF _ret IS NOT NULL
      THEN
        RETURN _ret;
      END IF;
    END LOOP;

    RETURN NULL;
  END;
$$;


ALTER FUNCTION matrix.search_refer_place(_uid bigint, _level integer) OWNER TO postgres;

--
-- Name: search_refer_place(bigint, smallint, smallint); Type: FUNCTION; Schema: matrix; Owner: postgres
--

CREATE FUNCTION search_refer_place(_uid bigint, _level smallint, _depth smallint) RETURNS bigint
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _referals int8[];
    _count int4;
    _i int8;
    _nid int8;
    _ret int8;
  BEGIN
    RAISE NOTICE 'search_refer_place(%,%,%)', _uid, _level, _depth;
    IF _depth < 1
    THEN
      RETURN NULL;
    END IF;
    
    SELECT INTO _count count(*) FROM users.referals WHERE inviter=_uid;
 
    IF _count = 0
    THEN
      RAISE NOTICE 'No invited founded';
      RETURN NULL;
    END IF;

    RAISE NOTICE 'Searching referals';

    FOR _i IN SELECT invited FROM users.referals WHERE inviter=_uid
    LOOP
      SELECT INTO _nid id FROM matrix.nodes WHERE uid=_i AND level=_level ORDER BY id DESC LIMIT 1;
      _ret = matrix.has_place(_nid, int2(2));
      RAISE NOTICE 'Check if direct child % has place at the matrix %', _i, _nid;
      IF _ret IS NOT NULL
      THEN
        RETURN _ret;
      END IF;
    END LOOP;
    
    RAISE NOTICE 'Search in direct childs failed';

    SELECT INTO _referals ARRAY(SELECT invited FROM users.referals WHERE inviter=_uid);

    FOR _i IN SELECT unnest(_referals)
    LOOP
      _ret = matrix.search_refer_place(_i, _level, int2(_depth - 1));
      IF _ret IS NOT NULL
      THEN
        RETURN _ret;
      END IF;
    END LOOP;

    RETURN NULL;
  END;
$$;


ALTER FUNCTION matrix.search_refer_place(_uid bigint, _level smallint, _depth smallint) OWNER TO postgres;

SET search_path = public, pg_catalog;

--
-- Name: array_alloc(integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION array_alloc(_size integer) RETURNS integer[]
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _i int4;
    _ret int4[];
  BEGIN
    FOR _i IN 1.._size
    LOOP
      _ret[_i] = 0;
    END LOOP;
    RETURN _ret;
  END;
$$;


ALTER FUNCTION public.array_alloc(_size integer) OWNER TO postgres;

--
-- Name: array_sum(integer[], integer[], integer); Type: FUNCTION; Schema: public; Owner: postgres
--

CREATE FUNCTION array_sum(_base integer[], _addon integer[], _offset integer) RETURNS integer[]
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _length int4;
    _i int4;
  BEGIN
    _length = array_length(_base, 1);
    IF array_length(_addon, 1) + _offset <> _length
    THEN
      RAISE EXCEPTION 'WRONG ARRAY MERGE SIZES(%,%) OFFSET (%)', _length, array_length(_addon, 1), _offset;
    END IF;

    --RAISE NOTICE 'array_sum(%,%,%)', _base, _addon, _offset;
    FOR _i IN 1+_offset.._length
    LOOP
      _base[_i] = _base[_i] + _addon[_i-_offset];
      --RAISE NOTICE 'array_sum(%,%,%) i=%', _base, _addon, _offset, _i;
    END LOOP;
    --RAISE NOTICE 'array_sum(%,%,%)', _base, _addon, _offset;    

    RETURN _base;
  END;
$$;


ALTER FUNCTION public.array_sum(_base integer[], _addon integer[], _offset integer) OWNER TO postgres;

SET search_path = users, pg_catalog;

--
-- Name: add_user_by_code(bigint, character varying); Type: FUNCTION; Schema: users; Owner: postgres
--

CREATE FUNCTION add_user_by_code(_id bigint, _code character varying) RETURNS bigint
    LANGUAGE plpgsql
    AS $_$
  DECLARE
    _request users.request%ROWTYPE;
    _uid int8;
    _ref int8;
    _res int8;
  BEGIN
    SELECT INTO _request * FROM users.request WHERE id = $1 AND code = $2;
    INSERT INTO users.logins(email, pass, phone) VALUES (_request.email, _request.pass, _request.phone) RETURNING INTO _uid id;

    IF _request.friend IS NOT NULL
    THEN
      INSERT INTO users.referals(inviter, invited) VALUES (_request.friend, _uid) RETURNING INTO _ref id;
      UPDATE users.logins SET ref=_ref WHERE id=_uid RETURNING INTO _res id;
      IF _res IS NULL OR _ref IS NULL OR _uid IS NULL
      THEN
        ROLLBACK;
        RETURN NULL;
      END IF;
    END IF;
    RETURN _uid;
  END;
$_$;


ALTER FUNCTION users.add_user_by_code(_id bigint, _code character varying) OWNER TO postgres;

--
-- Name: get_line_count(bigint, integer); Type: FUNCTION; Schema: users; Owner: postgres
--

CREATE FUNCTION get_line_count(_base_uid bigint, _level integer) RETURNS SETOF integer
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _uid int8;
    _referals int8[];
    _i int4;
    _ret int4[];
    _t int4[];
    _count int8;
  BEGIN
    RAISE NOTICE 'get_line_count(%,%)', _base_uid, _level;
    IF _level = 0
    THEN
      RAISE EXCEPTION 'Level in get_line_count cant be 0';
     END IF;

    _ret = array_alloc(int4(_level));
    FOR _i in 1.._level
    LOOP
      RAISE NOTICE 'get_line_count(%,%) init %', _base_uid, _level, _i;
      _ret[_i] = 0;
    END LOOP;
    
    SELECT INTO _count count(*) FROM users.referals WHERE inviter=_base_uid;
    
    IF _level = 1
    THEN
      RETURN NEXT _count;
      RETURN;
    END IF;
    

    IF _count = 0
    THEN
      FOR _i IN 1.._level
      LOOP
        RETURN NEXT 0;
      END LOOP;
        RETURN;      
    END IF;
    
    SELECT INTO _referals ARRAY(SELECT invited FROM users.referals WHERE inviter=_base_uid);

    RAISE NOTICE 'get_line_count(%,%) pre %', _base_uid, _level, _ret;
    _ret[1] = array_length(_referals, 1);
    FOR _i IN SELECT unnest(_referals)
    LOOP
      _t = ARRAY(SELECT users.get_line_count(_i::int8, _level - 1));
      RAISE NOTICE 'get_line_count(%,%) get %', _base_uid, _level, _t;
      --_t[_level] = 0;
      _ret = array_sum(_ret, _t, 1);
      RAISE NOTICE 'get_line_count(%,%) sum %', _base_uid, _level, _ret;
    END LOOP;
    
    RAISE NOTICE 'get_line_count(%,%) return %', _base_uid, _level, _ret;    

    RETURN QUERY SELECT unnest(_ret);
  END;
$$;


ALTER FUNCTION users.get_line_count(_base_uid bigint, _level integer) OWNER TO postgres;

--
-- Name: get_line_parents(bigint); Type: FUNCTION; Schema: users; Owner: postgres
--

CREATE FUNCTION get_line_parents(_base_uid bigint) RETURNS SETOF bigint
    LANGUAGE plpgsql
    AS $$
  DECLARE
    _inviter int8;
    _i int4;
    _uid int8;
  BEGIN
    _uid = _base_uid;
    FOR _i IN 1..5
    LOOP
      SELECT INTO _inviter inviter FROM users.referals WHERE id = (SELECT "ref" FROM users.logins WHERE id=_uid);
      RETURN NEXT _inviter;
      _uid = _inviter;
    END LOOP;
  END;
$$;


ALTER FUNCTION users.get_line_parents(_base_uid bigint) OWNER TO postgres;

SET search_path = finances, pg_catalog;

--
-- Name: accounts_id_seq; Type: SEQUENCE; Schema: finances; Owner: postgres
--

CREATE SEQUENCE accounts_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE finances.accounts_id_seq OWNER TO postgres;

--
-- Name: accounts; Type: TABLE; Schema: finances; Owner: postgres; Tablespace: 
--

CREATE TABLE accounts (
    id bigint DEFAULT nextval('accounts_id_seq'::regclass) NOT NULL,
    uid integer NOT NULL,
    value money,
    wallet character varying NOT NULL
);


ALTER TABLE finances.accounts OWNER TO postgres;

--
-- Name: sys_bills; Type: TABLE; Schema: finances; Owner: postgres; Tablespace: 
--

CREATE TABLE sys_bills (
    id bigint NOT NULL,
    quest bigint NOT NULL,
    amount bitcoin NOT NULL,
    tid bigint,
    wallet character varying,
    ip inet NOT NULL,
    snap timestamp without time zone DEFAULT now() NOT NULL,
    payed bitcoin DEFAULT 0 NOT NULL,
    line smallint,
    CONSTRAINT sys_bills_wallet_check CHECK (((wallet)::text <> ''::text))
);


ALTER TABLE finances.sys_bills OWNER TO postgres;

--
-- Name: COLUMN sys_bills.id; Type: COMMENT; Schema: finances; Owner: postgres
--

COMMENT ON COLUMN sys_bills.id IS 'unique bill id';


--
-- Name: COLUMN sys_bills.quest; Type: COMMENT; Schema: finances; Owner: postgres
--

COMMENT ON COLUMN sys_bills.quest IS 'quest id';


--
-- Name: COLUMN sys_bills.tid; Type: COMMENT; Schema: finances; Owner: postgres
--

COMMENT ON COLUMN sys_bills.tid IS 'target node id';


--
-- Name: COLUMN sys_bills.wallet; Type: COMMENT; Schema: finances; Owner: postgres
--

COMMENT ON COLUMN sys_bills.wallet IS 'destination wallet';


--
-- Name: COLUMN sys_bills.payed; Type: COMMENT; Schema: finances; Owner: postgres
--

COMMENT ON COLUMN sys_bills.payed IS 'actual payed value';


--
-- Name: bills; Type: VIEW; Schema: finances; Owner: postgres
--

CREATE VIEW bills AS
    SELECT sys_bills.id, sys_bills.quest, sys_bills.amount, sys_bills.tid, sys_bills.wallet, sys_bills.ip, sys_bills.snap, sys_bills.payed, ((sys_bills.payed)::numeric >= (sys_bills.amount)::numeric) AS completed FROM sys_bills;


ALTER TABLE finances.bills OWNER TO postgres;

--
-- Name: active_bills; Type: VIEW; Schema: finances; Owner: postgres
--

CREATE VIEW active_bills AS
    SELECT bills.id, bills.quest, bills.amount, bills.tid, bills.wallet, bills.ip, bills.snap, bills.payed, bills.completed FROM bills WHERE (bills.completed = false);


ALTER TABLE finances.active_bills OWNER TO postgres;

--
-- Name: bills_id_seq; Type: SEQUENCE; Schema: finances; Owner: postgres
--

CREATE SEQUENCE bills_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE finances.bills_id_seq OWNER TO postgres;

--
-- Name: quests_id_seq; Type: SEQUENCE; Schema: finances; Owner: postgres
--

CREATE SEQUENCE quests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE finances.quests_id_seq OWNER TO postgres;

--
-- Name: quests; Type: TABLE; Schema: finances; Owner: postgres; Tablespace: 
--

CREATE TABLE quests (
    id bigint DEFAULT nextval('quests_id_seq'::regclass) NOT NULL,
    uid bigint NOT NULL,
    nid bigint NOT NULL,
    ip inet NOT NULL,
    snap timestamp without time zone DEFAULT now() NOT NULL,
    tx character varying,
    tx_snap timestamp without time zone
);


ALTER TABLE finances.quests OWNER TO postgres;

--
-- Name: quest_status; Type: VIEW; Schema: finances; Owner: postgres
--

CREATE VIEW quest_status AS
    WITH quest_stats AS (SELECT bills.quest AS qid, count(*) AS total, sum((bills.completed)::integer) AS completed, sum((bills.amount)::numeric) AS amount, sum((bills.payed)::numeric) AS payed FROM bills GROUP BY bills.quest) SELECT quests.id, quests.uid, quests.nid, quests.ip, quests.snap, quest_stats.qid, quest_stats.total, quest_stats.completed, quest_stats.amount, quest_stats.payed FROM (quests JOIN quest_stats ON ((quests.id = quest_stats.qid)));


ALTER TABLE finances.quest_status OWNER TO postgres;

--
-- Name: sys_bills_id_seq1; Type: SEQUENCE; Schema: finances; Owner: postgres
--

CREATE SEQUENCE sys_bills_id_seq1
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE finances.sys_bills_id_seq1 OWNER TO postgres;

--
-- Name: sys_bills_id_seq1; Type: SEQUENCE OWNED BY; Schema: finances; Owner: postgres
--

ALTER SEQUENCE sys_bills_id_seq1 OWNED BY sys_bills.id;


SET search_path = public, pg_catalog;

--
-- Name: feedback; Type: TABLE; Schema: public; Owner: postgres; Tablespace: 
--

CREATE TABLE feedback (
    id bigint NOT NULL,
    email character varying NOT NULL,
    text text NOT NULL,
    ip inet NOT NULL,
    snap timestamp without time zone DEFAULT now() NOT NULL,
    prev bigint,
    uid bigint
);


ALTER TABLE public.feedback OWNER TO postgres;

--
-- Name: feedback_id_seq; Type: SEQUENCE; Schema: public; Owner: postgres
--

CREATE SEQUENCE feedback_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.feedback_id_seq OWNER TO postgres;

--
-- Name: feedback_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: postgres
--

ALTER SEQUENCE feedback_id_seq OWNED BY feedback.id;


SET search_path = users, pg_catalog;

--
-- Name: logins_id_seq; Type: SEQUENCE; Schema: users; Owner: postgres
--

CREATE SEQUENCE logins_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE users.logins_id_seq OWNER TO postgres;

--
-- Name: logins; Type: TABLE; Schema: users; Owner: postgres; Tablespace: 
--

CREATE TABLE logins (
    id bigint DEFAULT nextval('logins_id_seq'::regclass) NOT NULL,
    email character varying NOT NULL,
    pass character varying NOT NULL,
    ref bigint,
    phone character varying NOT NULL
);


ALTER TABLE users.logins OWNER TO postgres;

--
-- Name: referals; Type: TABLE; Schema: users; Owner: postgres; Tablespace: 
--

CREATE TABLE referals (
    id bigint NOT NULL,
    inviter bigint NOT NULL,
    invited bigint NOT NULL,
    CONSTRAINT referals_check CHECK ((inviter <> invited))
);


ALTER TABLE users.referals OWNER TO postgres;

--
-- Name: referals_id_seq; Type: SEQUENCE; Schema: users; Owner: postgres
--

CREATE SEQUENCE referals_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE users.referals_id_seq OWNER TO postgres;

--
-- Name: referals_id_seq; Type: SEQUENCE OWNED BY; Schema: users; Owner: postgres
--

ALTER SEQUENCE referals_id_seq OWNED BY referals.id;


--
-- Name: request_id_seq; Type: SEQUENCE; Schema: users; Owner: postgres
--

CREATE SEQUENCE request_id_seq
    START WITH 9
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE users.request_id_seq OWNER TO postgres;

--
-- Name: request; Type: TABLE; Schema: users; Owner: postgres; Tablespace: 
--

CREATE TABLE request (
    id bigint DEFAULT nextval('request_id_seq'::regclass) NOT NULL,
    pass character varying NOT NULL,
    email character varying NOT NULL,
    ip inet NOT NULL,
    snap timestamp(6) without time zone DEFAULT now() NOT NULL,
    code character varying NOT NULL,
    mail_verified boolean DEFAULT false NOT NULL,
    friend bigint,
    phone character varying NOT NULL
);


ALTER TABLE users.request OWNER TO postgres;

SET search_path = finances, pg_catalog;

--
-- Name: id; Type: DEFAULT; Schema: finances; Owner: postgres
--

ALTER TABLE ONLY sys_bills ALTER COLUMN id SET DEFAULT nextval('sys_bills_id_seq1'::regclass);


SET search_path = public, pg_catalog;

--
-- Name: id; Type: DEFAULT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY feedback ALTER COLUMN id SET DEFAULT nextval('feedback_id_seq'::regclass);


SET search_path = users, pg_catalog;

--
-- Name: id; Type: DEFAULT; Schema: users; Owner: postgres
--

ALTER TABLE ONLY referals ALTER COLUMN id SET DEFAULT nextval('referals_id_seq'::regclass);


SET search_path = finances, pg_catalog;

--
-- Data for Name: accounts; Type: TABLE DATA; Schema: finances; Owner: postgres
--

COPY accounts (id, uid, value, wallet) FROM stdin;
\.


--
-- Name: accounts_id_seq; Type: SEQUENCE SET; Schema: finances; Owner: postgres
--

SELECT pg_catalog.setval('accounts_id_seq', 1, false);


--
-- Name: bills_id_seq; Type: SEQUENCE SET; Schema: finances; Owner: postgres
--

SELECT pg_catalog.setval('bills_id_seq', 1, false);


--
-- Data for Name: quests; Type: TABLE DATA; Schema: finances; Owner: postgres
--

COPY quests (id, uid, nid, ip, snap, tx, tx_snap) FROM stdin;
\.


--
-- Name: quests_id_seq; Type: SEQUENCE SET; Schema: finances; Owner: postgres
--

SELECT pg_catalog.setval('quests_id_seq', 1, false);


--
-- Data for Name: sys_bills; Type: TABLE DATA; Schema: finances; Owner: postgres
--

COPY sys_bills (id, quest, amount, tid, wallet, ip, snap, payed, line) FROM stdin;
\.


--
-- Name: sys_bills_id_seq1; Type: SEQUENCE SET; Schema: finances; Owner: postgres
--

SELECT pg_catalog.setval('sys_bills_id_seq1', 1, false);


SET search_path = matrix, pg_catalog;

--
-- Data for Name: nodes; Type: TABLE DATA; Schema: matrix; Owner: postgres
--

COPY nodes (id, uid, parent, childs, ip, snap, level, commited) FROM stdin;
\.


--
-- Name: nods_id_seq; Type: SEQUENCE SET; Schema: matrix; Owner: postgres
--

SELECT pg_catalog.setval('nods_id_seq', 1, false);


SET search_path = public, pg_catalog;

--
-- Data for Name: feedback; Type: TABLE DATA; Schema: public; Owner: postgres
--

COPY feedback (id, email, text, ip, snap, prev, uid) FROM stdin;
\.


--
-- Name: feedback_id_seq; Type: SEQUENCE SET; Schema: public; Owner: postgres
--

SELECT pg_catalog.setval('feedback_id_seq', 1, false);


SET search_path = users, pg_catalog;

--
-- Data for Name: logins; Type: TABLE DATA; Schema: users; Owner: postgres
--

COPY logins (id, email, pass, ref, phone) FROM stdin;
\.


--
-- Name: logins_id_seq; Type: SEQUENCE SET; Schema: users; Owner: postgres
--

SELECT pg_catalog.setval('logins_id_seq', 1, false);


--
-- Data for Name: referals; Type: TABLE DATA; Schema: users; Owner: postgres
--

COPY referals (id, inviter, invited) FROM stdin;
\.


--
-- Name: referals_id_seq; Type: SEQUENCE SET; Schema: users; Owner: postgres
--

SELECT pg_catalog.setval('referals_id_seq', 1, false);


--
-- Data for Name: request; Type: TABLE DATA; Schema: users; Owner: postgres
--

COPY request (id, pass, email, ip, snap, code, mail_verified, friend, phone) FROM stdin;
\.


--
-- Name: request_id_seq; Type: SEQUENCE SET; Schema: users; Owner: postgres
--

SELECT pg_catalog.setval('request_id_seq', 1, false);


SET search_path = finances, pg_catalog;

--
-- Name: accounts_pkey; Type: CONSTRAINT; Schema: finances; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY accounts
    ADD CONSTRAINT accounts_pkey PRIMARY KEY (id);


--
-- Name: quests_pkey; Type: CONSTRAINT; Schema: finances; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY quests
    ADD CONSTRAINT quests_pkey PRIMARY KEY (id);


--
-- Name: sys_bills_id_key; Type: CONSTRAINT; Schema: finances; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY sys_bills
    ADD CONSTRAINT sys_bills_id_key UNIQUE (id);


--
-- Name: sys_bills_pkey; Type: CONSTRAINT; Schema: finances; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY sys_bills
    ADD CONSTRAINT sys_bills_pkey PRIMARY KEY (id, quest);


SET search_path = matrix, pg_catalog;

--
-- Name: nodes_id_key; Type: CONSTRAINT; Schema: matrix; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY nodes
    ADD CONSTRAINT nodes_id_key UNIQUE (id);


--
-- Name: nodes_pkey; Type: CONSTRAINT; Schema: matrix; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY nodes
    ADD CONSTRAINT nodes_pkey PRIMARY KEY (id, uid);


SET search_path = public, pg_catalog;

--
-- Name: feedback_pkey; Type: CONSTRAINT; Schema: public; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY feedback
    ADD CONSTRAINT feedback_pkey PRIMARY KEY (id, email);


SET search_path = users, pg_catalog;

--
-- Name: logins_email_key; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY logins
    ADD CONSTRAINT logins_email_key UNIQUE (email);


--
-- Name: logins_id_key; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY logins
    ADD CONSTRAINT logins_id_key UNIQUE (id);


--
-- Name: logins_pkey; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY logins
    ADD CONSTRAINT logins_pkey PRIMARY KEY (id, email);


--
-- Name: logins_ref_key; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY logins
    ADD CONSTRAINT logins_ref_key UNIQUE (ref);


--
-- Name: referals_id_key; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY referals
    ADD CONSTRAINT referals_id_key UNIQUE (id);


--
-- Name: referals_invited_key; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY referals
    ADD CONSTRAINT referals_invited_key UNIQUE (invited);


--
-- Name: referals_pkey; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY referals
    ADD CONSTRAINT referals_pkey PRIMARY KEY (id, inviter, invited);


--
-- Name: request_pkey; Type: CONSTRAINT; Schema: users; Owner: postgres; Tablespace: 
--

ALTER TABLE ONLY request
    ADD CONSTRAINT request_pkey PRIMARY KEY (id, email, snap);


SET search_path = matrix, pg_catalog;

--
-- Name: append_parent; Type: TRIGGER; Schema: matrix; Owner: postgres
--

CREATE TRIGGER append_parent BEFORE INSERT OR UPDATE ON nodes FOR EACH ROW EXECUTE PROCEDURE append_parent();


--
-- Name: commited_childs; Type: TRIGGER; Schema: matrix; Owner: postgres
--

CREATE TRIGGER commited_childs BEFORE UPDATE ON nodes FOR EACH ROW EXECUTE PROCEDURE commited_childs();


--
-- Name: commited_status_const; Type: TRIGGER; Schema: matrix; Owner: postgres
--

CREATE TRIGGER commited_status_const BEFORE UPDATE ON nodes FOR EACH ROW EXECUTE PROCEDURE commited_status_const();


--
-- Name: max_count; Type: TRIGGER; Schema: matrix; Owner: postgres
--

CREATE TRIGGER max_count BEFORE INSERT OR UPDATE ON nodes FOR EACH ROW EXECUTE PROCEDURE level_count_constrait();


--
-- Name: remove_parent; Type: TRIGGER; Schema: matrix; Owner: postgres
--

CREATE TRIGGER remove_parent BEFORE DELETE ON nodes FOR EACH ROW EXECUTE PROCEDURE remove_parent();


SET search_path = finances, pg_catalog;

--
-- Name: quests_nid_fkey; Type: FK CONSTRAINT; Schema: finances; Owner: postgres
--

ALTER TABLE ONLY quests
    ADD CONSTRAINT quests_nid_fkey FOREIGN KEY (nid) REFERENCES matrix.nodes(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: quests_uid_fkey; Type: FK CONSTRAINT; Schema: finances; Owner: postgres
--

ALTER TABLE ONLY quests
    ADD CONSTRAINT quests_uid_fkey FOREIGN KEY (uid) REFERENCES users.logins(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: sys_bills_quest_fkey; Type: FK CONSTRAINT; Schema: finances; Owner: postgres
--

ALTER TABLE ONLY sys_bills
    ADD CONSTRAINT sys_bills_quest_fkey FOREIGN KEY (quest) REFERENCES quests(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: CONSTRAINT sys_bills_quest_fkey ON sys_bills; Type: COMMENT; Schema: finances; Owner: postgres
--

COMMENT ON CONSTRAINT sys_bills_quest_fkey ON sys_bills IS 'if quest outdated, all bills removed';


--
-- Name: sys_bills_tid_fkey; Type: FK CONSTRAINT; Schema: finances; Owner: postgres
--

ALTER TABLE ONLY sys_bills
    ADD CONSTRAINT sys_bills_tid_fkey FOREIGN KEY (tid) REFERENCES users.logins(id) ON UPDATE CASCADE ON DELETE RESTRICT;


SET search_path = matrix, pg_catalog;

--
-- Name: nodes_uid_fkey; Type: FK CONSTRAINT; Schema: matrix; Owner: postgres
--

ALTER TABLE ONLY nodes
    ADD CONSTRAINT nodes_uid_fkey FOREIGN KEY (uid) REFERENCES users.logins(id) ON UPDATE CASCADE ON DELETE RESTRICT;


SET search_path = public, pg_catalog;

--
-- Name: feedback_uid_fkey; Type: FK CONSTRAINT; Schema: public; Owner: postgres
--

ALTER TABLE ONLY feedback
    ADD CONSTRAINT feedback_uid_fkey FOREIGN KEY (uid) REFERENCES users.logins(id) ON UPDATE CASCADE ON DELETE RESTRICT;


SET search_path = users, pg_catalog;

--
-- Name: logins_ref_fkey; Type: FK CONSTRAINT; Schema: users; Owner: postgres
--

ALTER TABLE ONLY logins
    ADD CONSTRAINT logins_ref_fkey FOREIGN KEY (ref) REFERENCES referals(id) ON UPDATE CASCADE ON DELETE CASCADE;


--
-- Name: referals_invited_fkey; Type: FK CONSTRAINT; Schema: users; Owner: postgres
--

ALTER TABLE ONLY referals
    ADD CONSTRAINT referals_invited_fkey FOREIGN KEY (invited) REFERENCES logins(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: referals_inviter_fkey; Type: FK CONSTRAINT; Schema: users; Owner: postgres
--

ALTER TABLE ONLY referals
    ADD CONSTRAINT referals_inviter_fkey FOREIGN KEY (inviter) REFERENCES logins(id) ON UPDATE CASCADE ON DELETE RESTRICT;


--
-- Name: public; Type: ACL; Schema: -; Owner: postgres
--

REVOKE ALL ON SCHEMA public FROM PUBLIC;
REVOKE ALL ON SCHEMA public FROM postgres;
GRANT ALL ON SCHEMA public TO postgres;
GRANT ALL ON SCHEMA public TO PUBLIC;


--
-- PostgreSQL database dump complete
--

