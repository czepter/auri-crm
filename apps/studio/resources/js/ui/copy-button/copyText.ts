function selectionCopy(value: string): boolean {
  const area = document.createElement("textarea")
  area.value = value
  area.setAttribute("readonly", "")
  area.style.cssText = "position:fixed;top:0;opacity:0"
  document.body.appendChild(area)
  area.select()
  try {
    return document.execCommand("copy")
  }
  finally {
    area.remove()
  }
}

export async function copyText(value: string): Promise<boolean> {
  if (value === "") return false

  try {
    await navigator.clipboard.writeText(value)
    return true
  }
  catch {
    return selectionCopy(value)
  }
}
