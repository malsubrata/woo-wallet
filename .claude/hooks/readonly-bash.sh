#!/usr/bin/env bash
# PreToolUse hook for the read-only review agents (see `hooks:` in their frontmatter).
# Blocks any Bash command that is not plain investigation. "Read-only" in an agent prompt
# is a request; this is the control. Exit 2 = tool call refused, stderr goes to the agent.
#
# ponytail: an allowlist of command prefixes, not a shell parser. It fails closed — anything
# it cannot recognise is refused. Add a prefix here if a reviewer needs one.

cmd=$(jq -r '.tool_input.command // empty')

deny() {
	echo "Blocked by .claude/hooks/readonly-bash.sh: $1. This agent is read-only: git diff/log/show/status/rev-parse/ls-files/blame/merge-base, grep, rg, ls, wc, head, tail, cat only. Report the finding; the main session acts." >&2
	exit 2
}

[ -n "$cmd" ] || deny "empty or unreadable command"

# Command substitution is checked on the raw command: it executes even inside double quotes.
case "$cmd" in
	*'`'* | *'$('* | *'<('*) deny "command substitution" ;;
	*--output*) deny "--output writes a file" ;;
esac

# Quoted strings are dropped before the remaining checks, so a pattern such as
# grep -E 'insert|update' or grep '->insert' is not mistaken for a pipe or a redirect.
bare=$(printf '%s' "$cmd" | sed -E "s/'[^']*'//g; s/\"[^\"]*\"//g")
case "$bare" in
	*'>'* | *"'"* | *'"'*) deny "redirection or unbalanced quote" ;;
esac

# Every segment of a pipeline / chain must start with an allowed command.
allowed='^(git( -C [^ ]+)?( --no-pager)? (diff|log|show|status|rev-parse|ls-files|blame|merge-base)|grep|rg|ls|wc|head|tail|cat)( |$)'
while IFS= read -r segment; do
	segment="${segment#"${segment%%[![:space:]]*}"}"
	[ -z "$segment" ] && continue
	[[ "$segment" =~ $allowed ]] || deny "'$segment' is not an allowed command"
done < <(printf '%s\n' "$bare" | sed -E 's/(\|\||&&|[|;&])/\n/g')

exit 0
