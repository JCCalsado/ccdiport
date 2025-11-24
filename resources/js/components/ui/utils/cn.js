export function cn(...inputs) {
  return inputs
    .flat()
    .filter(Boolean)
    .join(' ')
    .split(' ')
    .filter((value, index, self) => self.indexOf(value) === index)
    .join(' ');
}