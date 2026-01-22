import argparse
import getpass
import uvicorn
from fastapi import FastAPI, File, UploadFile, Form, HTTPException
from fastapi.responses import JSONResponse
from PIL import Image
import numpy as np
import io
import mysql.connector
from typing import Optional
import json
import time
import faiss
import threading
import torch
import clip
import requests
import hashlib, os, time
import tempfile

# Command-line arguments
parser = argparse.ArgumentParser(description="CLIP search service for ResourceSpace")
parser.add_argument("--dbuser", help="MySQL username")
parser.add_argument("--dbpass", help="MySQL password")
parser.add_argument("--host", default="0.0.0.0", help="Host to run on")
parser.add_argument("--port", type=int, default=8000, help="Port to run on")
args = parser.parse_args()

# Prefer environment variables if set
db_user = os.getenv("CLIP_DB_USER", args.dbuser)
db_pass = os.getenv("CLIP_DB_PASS", args.dbpass)

# Fall back to prompts if still not set
if not db_user:
    db_user = input("Enter MySQL username (or set CLIP_DB_USER / use --dbuser): ")

if not db_pass:
    db_pass = getpass.getpass("Enter MySQL password (or set CLIP_DB_PASS / use --dbpass): ")

# Global DB credentials (used later)
DB_CONFIG = {
    "host": "localhost",
    "user": db_user,
    "password": db_pass
}

# Set up FastAPI app and in-memory cache
app = FastAPI()
device = "cpu"
print("Loading CLIP model...")
model, preprocess = clip.load("ViT-B/32", device=device)
print("Model loaded.")

cached_vectors = {}       # { db_name: (vectors_np, resource_ids) }
loaded_max_ref = {}       # { db_name: max_ref }
faiss_indexes = {}        # { db_name: faiss.IndexFlatIP }
tag_vector_cache = {}        # { url: (tag_list, tag_vectors) }
tag_faiss_index_cache = {}   # { url: faiss.IndexFlatIP }

def load_vectors_for_db(db_name, force_reload=False):
    global cached_vectors, loaded_max_ref, faiss_indexes

    if db_name in cached_vectors and not force_reload:
        return cached_vectors[db_name]

    print(f"Loading vectors from DB: {db_name}")
    try:
        conn = mysql.connector.connect(**DB_CONFIG, database=db_name)
        cursor = conn.cursor()

        last_ref = loaded_max_ref.get(db_name, 0)

        start = time.time()
        cursor.execute(
            "SELECT ref, resource, vector_blob FROM resource_clip_vector WHERE is_text=0 AND ref > %s ORDER BY resource",
            (last_ref,)
        )
        rows = cursor.fetchall()
        conn.close()
        elapsed_ms = round((time.time() - start) * 1000)
        print(f"Vector load from MySQL took {elapsed_ms}ms")
        start = time.time()

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"DB error: {e}")

    if not rows and db_name in cached_vectors:
        return cached_vectors[db_name]

    new_vectors = []
    new_ids = []

    for ref, resource, blob in rows:
        if resource is None:
            print(f"Skipping ref {ref}: resource is None")
            continue

        if len(blob) != 2048:
            print(f"Skipping ref {ref} (resource {resource}): blob is {len(blob)} bytes, expected 2048")
            continue

        try:
            vector = np.frombuffer(blob, dtype=np.float32).copy()
            if vector.shape != (512,):
                print(f"Skipping ref {ref} (resource {resource}): vector shape {vector.shape}, expected (512,)")
                continue

            norm = np.linalg.norm(vector)
            if norm == 0 or np.isnan(norm):
                print(f"Skipping ref {ref} (resource {resource}): invalid norm ({norm})")
                continue

            vector /= norm
            new_vectors.append(vector)
            new_ids.append(resource)

        except Exception as e:
            print(f"Exception parsing vector for ref {ref} (resource {resource}): {e}")
            continue

    if db_name in cached_vectors:
        old_vectors, old_ids = cached_vectors[db_name]
        vectors = np.vstack([old_vectors, new_vectors])
        ids = old_ids + new_ids
    else:
        vectors = np.stack(new_vectors) if new_vectors else np.empty((0, 512), dtype=np.float32)
        ids = new_ids

    cached_vectors[db_name] = (vectors, ids)
    if ids:
        loaded_max_ref[db_name] = max(ref for ref, _, _ in rows)

    # Rebuild or update FAISS index
    if db_name not in faiss_indexes:
        index = faiss.IndexFlatIP(512)
        if len(vectors) > 0:
            index.add(vectors)
        faiss_indexes[db_name] = index
    else:
        if new_vectors:
            faiss_indexes[db_name].add(np.stack(new_vectors))

    elapsed_ms = round((time.time() - start) * 1000)
    print(f"Vector processing and indexing took {elapsed_ms}ms")
    print(f"Cached {len(ids)} vectors for DB: {db_name}")
    return cached_vectors[db_name]





@app.post("/vector")
async def generate_vector(
    image: Optional[UploadFile] = File(None),
    text: Optional[str] = Form(None),
):
    if image is None and text is None:
        raise HTTPException(status_code=400, detail="Provide either 'image' or 'text'")

    try:
        if image:
            contents = await image.read()
            img = Image.open(io.BytesIO(contents)).convert("RGB")
            img_input = preprocess(img).unsqueeze(0).to(device)

            with torch.no_grad():
                vector = model.encode_image(img_input)
        else:
            tokens = clip.tokenize([text]).to(device)
            with torch.no_grad():
                vector = model.encode_text(tokens)

        # Normalise and return vector
        vector = vector / vector.norm(dim=-1, keepdim=True)
        vector_np = vector.cpu().numpy().flatten().tolist()
        return JSONResponse(content=vector_np)

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Vector generation error: {e}")



@app.post("/search")
async def search(
    db: str = Form(...),
    text: str = Form(None),
    image: UploadFile = File(None),
    resource: int = Form(None),
    ref: str = Form(None),
    top_k: int = Form(5)
):
    if not any([text, image, resource, ref]):
        raise HTTPException(status_code=400, detail="Provide one of: text, image, resource, or ref")

    print(f"SEARCH: db={db}, top_k={top_k}")
    vectors, resource_ids = load_vectors_for_db(db)
    print(f"Vectors loaded: {len(resource_ids)} resources")

    if len(resource_ids) == 0:
        return JSONResponse(content=[])

    try:
        index = faiss_indexes.get(db)
        if not index:
            raise HTTPException(status_code=500, detail="FAISS index not found")

        # --- Create query vector ---
        if text:
            print("Text query")
            tokens = clip.tokenize([text]).to(device)
            with torch.no_grad():
                query_vector = model.encode_text(tokens)

        elif image:
            print("Image query")
            contents = await image.read()
            img = Image.open(io.BytesIO(contents)).convert("RGB")
            img_input = preprocess(img).unsqueeze(0).to(device)
            with torch.no_grad():
                query_vector = model.encode_image(img_input)

        elif resource is not None or ref is not None:
            # Determine which field to query
            column = "resource" if resource is not None else "ref"
            value = resource if resource is not None else ref
            print(f"Query from DB vector: {column} = {value}")

            conn = mysql.connector.connect(**DB_CONFIG, database=db)
            cursor = conn.cursor()
            cursor.execute(f"SELECT vector_blob FROM resource_clip_vector WHERE {column} = %s AND is_text=0", (value,))
            row = cursor.fetchone()
            conn.close()

            if not row or not row[0] or len(row[0]) != 2048:
                raise HTTPException(status_code=404, detail=f"Valid vector_blob not found for {column}={value}")

            query_vector = np.frombuffer(row[0], dtype=np.float32).copy()
            if query_vector.shape != (512,):
                raise HTTPException(status_code=400, detail="Malformed vector shape")
            norm = np.linalg.norm(query_vector)
            if norm == 0 or np.isnan(norm):
                raise HTTPException(status_code=400, detail="Invalid vector norm")
            query_vector = torch.tensor(query_vector).unsqueeze(0)

        else:
            raise HTTPException(status_code=400, detail="Invalid input combination")

        # --- Search ---
        query_vector = query_vector / query_vector.norm(dim=-1, keepdim=True)
        query_np = query_vector.cpu().numpy().flatten()
        print("Query vector created")

        print("Performing FAISS search")
        print(f"FAISS index size: {index.ntotal}")
        start = time.time()
        D, I = index.search(query_np.reshape(1, -1), top_k + 1)
        elapsed_ms = round((time.time() - start) * 1000)
        print(f"FAISS search took {elapsed_ms}ms")
        print(f"Search results: {I[0]}")

        results = []
        ref_int = int(ref) if ref not in (None, "") else None

        for i, score in zip(I[0], D[0]):
            if i < 0:
                continue
            candidate_id = int(resource_ids[i])

            # Skip self-match for resource/ref queries
            if (resource is not None and candidate_id == resource) or \
               (ref_int is not None and candidate_id == ref_int):
                continue


            results.append({
                "resource": candidate_id,
                "score": float(score)
            })

            if len(results) == top_k:
                break

        print("Returning", len(results), "results")
        return JSONResponse(content=results)

    except Exception as e:
        print(f"Exception in /search: {e}")
        raise HTTPException(status_code=500, detail=f"Search error: {e}")




@app.post("/duplicates")
async def find_duplicates(
    db: str = Form(...),
    threshold: float = Form(0.9)
):
    vectors, resource_ids = load_vectors_for_db(db)

    if len(resource_ids) == 0:
        return JSONResponse(content=[])

    try:
        index = faiss_indexes.get(db)
        if not index:
            raise HTTPException(status_code=500, detail="FAISS index not found")

        results = []
        top_k = 50  # You can increase this if you want more candidates

        D, I = index.search(vectors, top_k)  # batch search all against all

        for i, (distances, indices) in enumerate(zip(D, I)):
            for j, score in zip(indices, distances):
                if score >= threshold and i != j:
                    a = int(resource_ids[i])
                    b = int(resource_ids[j])
                    if a < b:  # avoid duplicates (a,b) vs (b,a)
                        results.append({
                            "resource": a,
                            "resource_match": b,
                            "score": float(score)
                        })

        return JSONResponse(content=results)

    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Duplicate detection error: {e}")




@app.post("/tag")
async def tag_search(
    db: str = Form(...),
    url: str = Form(...),
    top_k: int = Form(5),
    resource: Optional[int] = Form(None),
    vector: Optional[str] = Form(None)
):
    CACHE_DIR = os.path.join(tempfile.gettempdir(), "clip_tag_cache")
    os.makedirs(CACHE_DIR, exist_ok=True)

    def get_cache_filename(url):
        hash = hashlib.sha256(url.encode()).hexdigest()
        return os.path.join(CACHE_DIR, f"{hash}.tagdb")

    cache_path = get_cache_filename(url)
    cache_expiry_secs = 30 * 86400  # 30 days

    use_disk_cache = (
        os.path.exists(cache_path) and
        (time.time() - os.path.getmtime(cache_path)) < cache_expiry_secs
    )

    if not use_disk_cache:
        try:
            start = time.time()
            response = requests.get(url)
            response.raise_for_status()
            with open(cache_path, 'w', encoding='utf-8') as f:
                f.write(response.text)
            elapsed = int((time.time() - start) * 1000)
            print(f"Downloaded tag database from URL in {elapsed}ms: {url}")
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"Failed to download tag vectors: {e}")

    if url not in tag_vector_cache:
        try:
            start = time.time()
            with open(cache_path, 'r', encoding='utf-8') as f:
                lines = f.read().strip().split('\n')

            tags = []
            vectors = []
            for line in lines:
                parts = line.strip().split()
                if len(parts) != 513:
                    continue
                tag = parts[0]
                vector_arr = np.array([float(x) for x in parts[1:]], dtype=np.float32)
                norm = np.linalg.norm(vector_arr)
                if norm == 0 or np.isnan(norm):
                    continue
                vector_arr /= norm
                tags.append(tag)
                vectors.append(vector_arr)

            if not vectors:
                raise ValueError("No valid tag vectors found.")

            tag_vectors = np.stack(vectors)
            tag_vector_cache[url] = (tags, tag_vectors)
            index = faiss.IndexFlatIP(512)
            index.add(tag_vectors)
            tag_faiss_index_cache[url] = index
            elapsed = int((time.time() - start) * 1000)
            print(f"Loaded tag database from disk cache in {elapsed}ms: {url}")

        except Exception as e:
            raise HTTPException(status_code=500, detail=f"Failed to load tag vectors from cache: {e}")

    tags, tag_vectors = tag_vector_cache[url]
    index = tag_faiss_index_cache[url]

    if vector:
        try:
            vector_list = json.loads(vector)
            resource_vector = np.array(vector_list, dtype=np.float32)
            if resource_vector.shape != (512,):
                raise HTTPException(status_code=400, detail="Malformed input vector shape")
            norm = np.linalg.norm(resource_vector)
            if norm == 0 or np.isnan(norm):
                raise HTTPException(status_code=400, detail="Invalid input vector norm")
            resource_vector /= norm
        except Exception as e:
            raise HTTPException(status_code=400, detail=f"Invalid 'vector' input: {e}")

    elif resource is not None:
        try:
            conn = mysql.connector.connect(**DB_CONFIG, database=db)
            cursor = conn.cursor()
            cursor.execute(
                "SELECT vector_blob FROM resource_clip_vector WHERE resource = %s AND is_text = 0",
                (resource,)
            )
            row = cursor.fetchone()
            conn.close()
            if not row or not row[0] or len(row[0]) != 2048:
                raise HTTPException(status_code=404, detail="Valid vector_blob not found for the specified resource.")
            resource_vector = np.frombuffer(row[0], dtype=np.float32).copy()
            if resource_vector.shape != (512,):
                raise HTTPException(status_code=400, detail="Malformed vector shape.")
            norm = np.linalg.norm(resource_vector)
            if norm == 0 or np.isnan(norm):
                raise HTTPException(status_code=400, detail="Invalid vector norm.")
            resource_vector /= norm
        except Exception as e:
            raise HTTPException(status_code=500, detail=f"Error retrieving resource vector: {e}")

    else:
        raise HTTPException(status_code=400, detail="Either 'resource' or 'vector' must be provided.")

    try:
        D, I = index.search(resource_vector.reshape(1, -1), top_k)
        results = []
        for idx, score in zip(I[0], D[0]):
            if idx < 0 or idx >= len(tags):
                continue
            results.append({
                "tag": tags[idx],
                "score": float(score)
            })
        return JSONResponse(content=results)
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Error during tagging: {e}")





def background_vector_loader():
    while True:
        time.sleep(30)
        try:
            for db_name in cached_vectors.keys():
                load_vectors_for_db(db_name, force_reload=True)
        except Exception as e:
            print(f"Background update failed: {e}")

@app.on_event("startup")
def start_background_task():
    print("Starting background vector refresher thread...")
    thread = threading.Thread(target=background_vector_loader, daemon=True)
    thread.start()




# Start the server
if __name__ == "__main__":
    uvicorn.run(app, host=args.host, port=args.port, log_level="debug")

