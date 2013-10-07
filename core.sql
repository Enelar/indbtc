CREATE OR REPLACE FUNCTION matrix.get_cycles( _uid int4, _level int2 ) RETURNS SETOF matrix.nodes AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.get_all_cycles( _uid int4 ) RETURNS SETOF matrix.nodes AS
$$
  DECLARE
    _level int2;
  BEGIN
    FOR _level IN 0..7
    LOOP
      RETURN QUERY SELECT * FROM matrix.get_cycles(int4(_uid), int2(_level));
    END LOOP;   
  END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION users.get_line_parents( _base_uid int8 ) RETURNS SETOF int8 AS
$$
  DECLARE
    _inviter int8;
    _i int4;
    _uid int8;
  BEGIN
    _uid = _base_uid;
    FOR _i IN 1..5
    LOOP
      IF _uid IS NOT NULL
      THEN
        SELECT INTO _inviter inviter FROM users.referals WHERE id = (SELECT "ref" FROM users.logins WHERE id=_uid);
        RETURN NEXT _inviter;
        _uid = _inviter;
      ELSE
        RETURN NEXT NULL;
      END IF;
    END LOOP;
  END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION users.is_child_of( _uid int8, _parent int8 ) RETURNS int2 AS
$$
  DECLARE
    _parents int8[];
    _i int2;
  BEGIN
    SELECT INTO _parents ARRAY(SELECT users.get_line_parents(_uid));
    FOR _i IN 1..5
    LOOP
     IF _parents[_i] = _parent
     THEN
       RETURN _i;
     END IF;
    END LOOP;
    
    IF _uid = _parent
    THEN
      RETURN 0;
    END IF;
    
    RETURN NULL;
  END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION users.get_line_count( _base_uid int8, _level int4 ) RETURNS SETOF int4 AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION users.add_user_by_code( _id int8, _code varchar ) RETURNS int8 AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.search_refer_place( _uid int8, _level int2, _depth int2 ) RETURNS int8 AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.add_to_system( _uid int8, _level int2, _ip inet ) RETURNS int8 AS
$$
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
    
    _nid = matrix.debug_refer_place_exception(_uid, _level);
    IF _nid IS NULL
    THEN
      _nid = matrix.search_refer_place(_uid, _level, int2(5));    
    END IF;

    INSERT INTO matrix.nodes(uid, parent, ip, level) VALUES (_uid, _nid, _ip, _level) RETURNING INTO _ret id;

    RETURN _ret;
  END;
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.debug_refer_place_exception( _uid int8, _level int2 ) RETURNS int8 AS
$$
  BEGIN
    RETURN NULL;
  END;
$$
LANGUAGE plpgsql;