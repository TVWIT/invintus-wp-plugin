import { v4 as uuidv4 } from 'uuid';

export default function createUniqueId( prefix = '' ) {
  return `${prefix}-${uuidv4()}`;
}
