CREATE OR REPLACE FUNCTION matrix.append_parent() RETURNS trigger AS
$$
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
$$
STRICT
LANGUAGE plpgsql;

CREATE TRIGGER append_parent BEFORE UPDATE OR INSERT ON matrix.nodes
  FOR EACH ROW
  EXECUTE PROCEDURE matrix.append_parent();




CREATE OR REPLACE FUNCTION matrix.remove_parent() RETURNS trigger AS
$$
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
$$
STRICT
LANGUAGE plpgsql;

CREATE TRIGGER remove_parent BEFORE DELETE ON matrix.nodes
  FOR EACH ROW 
  EXECUTE PROCEDURE matrix.remove_parent();

CREATE OR REPLACE FUNCTION matrix.level_count_constrait() RETURNS trigger AS
$$
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
$$
STRICT
LANGUAGE plpgsql;

CREATE TRIGGER max_count BEFORE INSERT OR UPDATE ON matrix.nodes
  FOR EACH ROW 
  EXECUTE PROCEDURE matrix.level_count_constrait();

CREATE OR REPLACE FUNCTION matrix.commited_status_const() RETURNS trigger AS
$$
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
$$
STRICT
LANGUAGE plpgsql;

CREATE TRIGGER commited_status_const BEFORE UPDATE ON matrix.nodes
  FOR EACH ROW 
  EXECUTE PROCEDURE matrix.commited_status_const();

CREATE OR REPLACE FUNCTION matrix.commited_childs() RETURNS trigger AS
$$
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
$$
STRICT
LANGUAGE plpgsql;

CREATE TRIGGER commited_childs BEFORE UPDATE ON matrix.nodes
  FOR EACH ROW 
  EXECUTE PROCEDURE matrix.commited_childs();
