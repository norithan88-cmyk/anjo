const initialListings = [
  {
    "name": "仕出し・弁当・宴会 大見屋",
    "category": "飲食・仕出し",
    "keywords": "弁当 和食 宴会",
    "address": "安城市城南町1丁目16-3（旧掲載・要確認）",
    "phone": "0566-76-5005（旧掲載・要確認）",
    "status": "営業状況要確認",
    "statusClass": "changed",
    "verificationType": "pending",
    "reviewedAt": "2026-09-12",
    "note": "現在の公式情報を確認できていません。住所・電話は旧掲載情報です。閉店を意味するものではありません。",
    "source": "https://anjo-cci.or.jp/imgdb/kaihou/59.pdf",
    "sourceLabel": "旧掲載の参考資料"
  },
  {
    "name": "株式会社デッサン",
    "category": "建設・工業",
    "address": "安城市東別所町屋敷71番地",
    "phone": "電話番号は要確認",
    "status": "法人情報確認",
    "statusClass": "changed",
    "verificationType": "corporate",
    "checkedAt": "2026-09-12",
    "note": "Gビズインフォで法人名・本店所在地を確認。電話・営業状況・旧掲載名との関係は未確認です。",
    "source": "https://info.gbiz.go.jp/hojin/ichiran?hojinBango=6180302017082",
    "sourceLabel": "Gビズインフォ（法人情報）"
  }
];

const listings = [
  ...officialListings,
  ...initialListings.filter(item => !officialListings.some(current => current.name === item.name))
];

document.querySelector('#listing-count').textContent = `${listings.length}件の店舗・企業情報`;
const grid = document.querySelector('#listing-grid');
const empty = document.querySelector('#empty-state');
const summary = document.querySelector('#result-summary');
const input = document.querySelector('#search-input');
const categorySelect = document.querySelector('#category-select');
const sortSelect = document.querySelector('#sort-select');
const resetButton = document.querySelector('#reset-button');
const moreButton = document.querySelector('#more-button');
let activeCategory = 'すべて';
let visibleCount = 18;

function escapeHtml(value){return String(value).replace(/[&<>'"]/g,c=>({"&":"&amp;","<":"&lt;",">":"&gt;","'":"&#39;",'"':"&quot;"}[c]));}
function normalizeSearch(value){
  return String(value).normalize('NFKC').toLowerCase()
    .replace(/café|cafe/g,'カフェ')
    .replace(/[\s　・･（）()\-‐‑–—]/g,'');
}
function render(){
  const term = normalizeSearch(input.value.trim());
  const filtered = listings.filter(item => {
    const categoryMatch = activeCategory === 'すべて' || item.category === activeCategory;
    const text = normalizeSearch([item.name,item.former,item.category,item.keywords,item.address,item.phone].join(' '));
    return categoryMatch && (!term || text.includes(term));
  }).sort((a,b) => sortSelect.value === 'category'
    ? a.category.localeCompare(b.category,'ja') || a.name.localeCompare(b.name,'ja')
    : a.name.localeCompare(b.name,'ja'));
  const visible = filtered.slice(0,visibleCount);
  grid.innerHTML = visible.map(item => `<article class="listing-card">
    <div class="listing-meta"><span class="listing-category">${escapeHtml(item.category)}</span><span class="badge ${item.statusClass}">${escapeHtml(item.status)}</span></div>
    <h3>${escapeHtml(item.name)}</h3>${item.former?`<p class="former-name">${escapeHtml(item.former)}</p>`:''}
    <p class="address">住所　${escapeHtml(item.address)}</p><p class="phone">電話　${escapeHtml(item.phone)}</p>
    <p class="checked-date">${item.checkedAt ? `情報確認日：<time datetime="${escapeHtml(item.checkedAt)}">${escapeHtml(item.checkedAt.replaceAll('-','/'))}</time>` : `調査日：${escapeHtml(item.reviewedAt.replaceAll('-','/'))}（確認継続中）`}</p>
    <p class="listing-note">${escapeHtml(item.note)}</p>
    ${item.source
      ? `<a class="source-link" href="${escapeHtml(item.source)}" target="_blank" rel="noopener noreferrer">${escapeHtml(item.sourceLabel)} ↗</a>`
      : `<p class="source-label">${escapeHtml(item.sourceLabel)}</p>`}
  </article>`).join('');
  empty.hidden = filtered.length !== 0;
  moreButton.hidden = visible.length >= filtered.length;
  moreButton.textContent = `さらに表示（残り${filtered.length-visible.length}件）`;
  summary.textContent = filtered.length === visible.length
    ? `${filtered.length}件を表示中`
    : `${filtered.length}件中${visible.length}件を表示`;
}

document.querySelector('#search-form').addEventListener('submit',event=>{event.preventDefault();render();document.querySelector('#directory').scrollIntoView({behavior:'smooth'});});
document.querySelectorAll('[data-category]').forEach(button=>button.addEventListener('click',()=>{
  activeCategory=button.dataset.category;
  categorySelect.value=activeCategory;
  visibleCount=18;
  document.querySelectorAll('.chip').forEach(chip=>chip.classList.toggle('active',chip.dataset.category===activeCategory));
  document.querySelectorAll('.purpose-card').forEach(card=>card.classList.toggle('active',card.dataset.category===activeCategory));
  render();document.querySelector('#directory').scrollIntoView({behavior:'smooth'});
}));
categorySelect.addEventListener('change',()=>{
  activeCategory=categorySelect.value;visibleCount=18;
  document.querySelectorAll('.chip').forEach(chip=>chip.classList.toggle('active',chip.dataset.category===activeCategory));
  document.querySelectorAll('.purpose-card').forEach(card=>card.classList.toggle('active',card.dataset.category===activeCategory));render();
});
sortSelect.addEventListener('change',()=>{visibleCount=18;render();});
input.addEventListener('input',()=>{visibleCount=18;render();});
resetButton.addEventListener('click',()=>{
  input.value='';activeCategory='すべて';categorySelect.value='すべて';sortSelect.value='name';visibleCount=18;
  document.querySelectorAll('.chip').forEach(chip=>chip.classList.toggle('active',chip.dataset.category==='すべて'));
  document.querySelectorAll('.purpose-card').forEach(card=>card.classList.remove('active'));render();input.focus();
});
moreButton.addEventListener('click',()=>{visibleCount+=18;render();});
render();
