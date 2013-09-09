CREATE OR REPLACE FUNCTION public.array_sum( _base int4[], _addon int4[], _offset int4 ) RETURNS int4[] AS
$$
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
$$
LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION public.array_alloc( _size int4 ) RETURNS int4[] AS
$$
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
$$
LANGUAGE plpgsql;

create or replace function random_string(length integer) returns text as 
$$
declare
  chars text[] := '{2,3,4,5,6,7,8,9,A,B,D,E,F,G,I,H,J,K,L,M,N,P,Q,R,S,T,U,V,W,X,Y,Z}';
  result text := '';
  i integer := 0;
begin
  if length < 0 then
    raise exception 'Given length cannot be less than 0';
  end if;
  for i in 1..length loop
    result := result || chars[1+random()*(array_length(chars, 1)-1)];
  end loop;
  return result;
end;
$$ language plpgsql;
