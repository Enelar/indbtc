CREATE OR REPLACE FUNCTION matrix.count_childs( int8[] ) RETURNS int2 AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.get_parents( int8, int4 ) RETURNS SETOF int8 AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.get_childs( node int8, depth int4 ) RETURNS SETOF int8 AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.is_completed( node int8, depth int4 ) RETURNS bool AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION matrix.has_place( _nid int8, _depth int2 ) RETURNS int8 AS
$$
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
$$
LANGUAGE plpgsql;
